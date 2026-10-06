<?php

/**
 * This file is part of OpenEMR.
 *
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace PatientFilter;

use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Events\Appointments\AppointmentsFilterEvent;
use OpenEMR\Events\PatientDemographics\UpdateEvent;
use OpenEMR\Events\PatientDemographics\ViewEvent;
use OpenEMR\Events\PatientFinder\PatientFinderFilterEvent;
use OpenEMR\Services\UserService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Module for creating a blacklist on the patient finder, which can restrict certain
 * users from accessing certain patients
 *
 * @package PatientFilter
 * @author Ken Chapple <ken@mi-squared.com>
 * @copyright Copyright (c) 2019 Ken Chapple <ken@mi-squared.com>
 */
class Module
{
    /** @var list<int|string>|null every patient pid, loaded once for allow-list entries */
    private ?array $allPids = null;

    public function getAutoloaderConfig()
    {
        return [
            \Laminas\Loader\ClassMapAutoloader::class => [
                __DIR__ . '/autoload_classmap.php',
            ],
            \Laminas\Loader\StandardAutoloader::class => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,

                ],
            ],
        ];
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * load global variables foe every controllers
     * @param ModuleManager $manager
     */
    public function init(ModuleManager $manager)
    {
    }

    /**
     * @param MvcEvent $e
     *
     * Register our event listeners here
     */
    public function onBootstrap(MvcEvent $e)
    {
        // Get application service manager and get instance of event dispatcher
        $serviceManager = $e->getApplication()->getServiceManager();
        $oemrDispatcher = $serviceManager->get(EventDispatcherInterface::class);

        // listen for the filter event in the patient finder (hook located in main/finder/dynamic_finder_ajax.php)
        $oemrDispatcher->addListener(PatientFinderFilterEvent::EVENT_HANDLE, $this->filterPatientFinderByBlacklist(...));

        // listen for filter event in the appointments.inc.php library
        $oemrDispatcher->addListener(AppointmentsFilterEvent::EVENT_HANDLE, $this->filterAppointmentsByBlacklist(...));

        // listen for view and update events on the patient demographics screen (hooks located in
        // interface/patient_file/summary/demogrphics.php and
        // interface/patient_file/summary/demogrphics_full.php
        $oemrDispatcher->addListener(ViewEvent::EVENT_HANDLE, $this->checkBlacklistForViewAuth(...));
        $oemrDispatcher->addListener(UpdateEvent::EVENT_HANDLE, $this->checkBlacklistForUpdateAuth(...));
    }

    /**
     * @param $username
     * @return array
     *
     * Load the list of patients that this user cannot access from our blacklist file.
     * An entry with 'whitelist' instead allows only the listed patients and blocks all others.
     */
    public function getBlacklist($username)
    {
        $config = include __DIR__ . "/config/blacklist.php";
        $pids = [];
        foreach (is_array($config) ? $config : [] as $item) {
            if (!is_array($item) || ($item['username'] ?? null) !== $username) {
                continue;
            }
            if (is_array($item['whitelist'] ?? null)) {
                $allowed = [];
                foreach ($item['whitelist'] as $allowedPid) {
                    if (is_int($allowedPid) || is_string($allowedPid)) {
                        $allowed[(string) $allowedPid] = true;
                    }
                }
                foreach ($this->getAllPids() as $pid) {
                    if (!isset($allowed[(string) $pid])) {
                        $pids[] = $pid;
                    }
                }
            } elseif (is_array($item['blacklist'] ?? null)) {
                $pids = array_merge($pids, $item['blacklist']);
            }
        }

        return $pids;
    }

    /**
     * @return list<int|string>
     */
    private function getAllPids(): array
    {
        if ($this->allPids === null) {
            $this->allPids = [];
            foreach (QueryUtils::fetchTableColumn('SELECT `pid` FROM `patient_data`', 'pid') as $pid) {
                if (is_int($pid) || is_string($pid)) {
                    $this->allPids[] = $pid;
                }
            }
        }
        return $this->allPids;
    }

    /**
     * @param AppointmentsFilterEvent $appointmentsFilterEvent
     * @return AppointmentsFilterEvent
     *
     * Handler for the appointment fetching filter, which is used in patient tracker and other
     * places.
     *
     * This filter's query uses SQL binding.
     */
    public function filterAppointmentsByBlacklist(AppointmentsFilterEvent $appointmentsFilterEvent)
    {
        $userService = new UserService();
        $user = $userService->getCurrentlyLoggedInUser();
        if ($user !== false) {
            $patientsToHide = $this->getBlacklist($user['username']);
            if (count($patientsToHide)) {
                $filterString = "(p.pid IS NULL OR p.pid NOT IN (";
                foreach ($patientsToHide as $patientToHide) {
                    $filterString .= "?,";
                }
                $filterString = rtrim($filterString, ",");
                $filterString .= "))";
                $boundFilter = $appointmentsFilterEvent->getBoundFilter();
                $boundFilter->setFilterClause($filterString);
                $boundFilter->setBoundValues($patientsToHide);
            }
        }

        return $appointmentsFilterEvent;
    }

    /**
     * @param PatientFinderFilterEvent $event
     * @return PatientFinderFilterEvent
     *
     * Handler for the patient finder filter. This function looks at the blacklist
     * and hides the specified patients.
     *
     * This filter does not use binding
     */
    public function filterPatientFinderByBlacklist(PatientFinderFilterEvent $event)
    {
        $userService = new UserService();
        $user = $userService->getCurrentlyLoggedInUser();
        $patientsToHide = $this->getBlacklist($user['username']);

        // If there are patients to hide from this user, build a filter
        if (count($patientsToHide)) {
            $filterString = "(";
            foreach ($patientsToHide as $patientToHide) {
                $filterString .= "?,";
            }
            $filterString = rtrim($filterString, ",");
            $filterString .= ")";
            $where = " patient_data.pid NOT IN $filterString ";

            // Set the query part we constructed as the custom where, which will be appended to patient filter query
            $boundFilter = $event->getBoundFilter();
            $boundFilter->setFilterClause($where);
            $boundFilter->setBoundValues($patientsToHide);
        }

        return $event;
    }

    /**
     * @param ViewEvent $event
     *
     * Handler for the view event in patient demographics. If the patient is in the logged-in user's
     * blacklist, they will not have access.
     */
    public function checkBlacklistForViewAuth(ViewEvent $event)
    {
        $userService = new UserService();
        $user = $userService->getCurrentlyLoggedInUser();
        $patientsToHide = $this->getBlacklist($user['username']);
        if (in_array($event->getPid(), $patientsToHide)) {
            $event->setAuthorized(false);
        }
    }

    /**
     * @param UpdateEvent $event
     *
     * Handler for the update event in patient demographics. If the patient is in the logged-in user's
     * blacklist, they will not have access.
     */
    public function checkBlacklistForUpdateAuth(UpdateEvent $event)
    {
        $userService = new UserService();
        $user = $userService->getCurrentlyLoggedInUser();
        $patientsToHide = $this->getBlacklist($user['username']);
        if (in_array($event->getPid(), $patientsToHide)) {
            $event->setAuthorized(false);
        }
    }
}
