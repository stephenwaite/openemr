<?php

/**
 * Handles participant invitation emails sent out for inviting third party patients to a telehealth session.
 *
 * @package openemr
 * @link      http://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @copyright Copyright (c) 2022 Comlink Inc <https://comlinkinc.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Comlink\OpenEMR\Modules\TeleHealthModule\Services;

use Comlink\OpenEMR\Modules\TeleHealthModule\Events\TelehealthNotificationSendEvent;
use Comlink\OpenEMR\Modules\TeleHealthModule\Models\NotificationSendAddress;
use Comlink\OpenEMR\Modules\TeleHealthModule\TelehealthGlobalConfig;
use MyMailer;
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Services\LogoService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;

class TeleHealthParticipantInvitationMailerService
{
    const MESSAGE_ID_TELEHEALTH_EXISTING_PATIENT = 'comlink-telehealth-invitation-existing-patient';

    const MESSAGE_ID_TELEHEALTH_NEW_PATIENT = 'comlink-telehealth-invitation-new-patient';
    private $publicPathFQDN;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var TelehealthGlobalConfig
     */
    private $config;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    public function __construct(EventDispatcher $dispatcher, Environment $twig, $publicPathFQDN, TelehealthGlobalConfig $config)
    {
        $this->dispatcher = $dispatcher;
        $this->twig = $twig;
        $this->publicPathFQDN = $publicPathFQDN;
        $this->config = $config;
    }

    public function sendInvitationToExistingPatient($patient, $session, $thirdPartyLaunchAction)
    {
        $data = $this->getInvitationData($patient, $session, $thirdPartyLaunchAction);
        $htmlMsg = $this->twig->render('comlink/emails/telehealth-invitation-existing.html.twig', $data);
        $plainMsg = $this->twig->render('comlink/emails/telehealth-invitation-existing.text.twig', $data);
        $this->sendMessageToPatient(
            $htmlMsg,
            $plainMsg,
            $patient,
            $this->getJoinLink($session, $thirdPartyLaunchAction),
            self::MESSAGE_ID_TELEHEALTH_EXISTING_PATIENT
        );
    }

    public function sendInvitationToNewPatient($patient, $session, $thirdPartyLaunchAction)
    {
        $data = $this->getInvitationData($patient, $session, $thirdPartyLaunchAction);
        $htmlMsg = $this->twig->render('comlink/emails/telehealth-invitation-new.html.twig', $data);
        $plainMsg = $this->twig->render('comlink/emails/telehealth-invitation-new.text.twig', $data);

        $this->sendMessageToPatient(
            $htmlMsg,
            $plainMsg,
            $patient,
            $this->getJoinLink($session, $thirdPartyLaunchAction),
            self::MESSAGE_ID_TELEHEALTH_NEW_PATIENT
        );
    }

    private function getInvitationData($patient, $session, $thirdPartyLaunchAction)
    {
        $logoService = new LogoService();
        $logoPath = $this->config->getQualifiedSiteAddress() . $logoService->getLogo('core/login/primary');
        $name = $this->config->getOpenEMRName();
        $data = [
            'url' => $this->getJoinLink()
            ,'pc_eid' => $session['pc_eid']
            ,'launchAction' => $thirdPartyLaunchAction
            ,'salutation' => ($patient['fname'] ?? '') . ' ' . ($patient['lname'] ?? '')
            ,'logoPath' => $logoPath
            ,'logoAlt' => $name ?? 'OpenEMR'
            ,'title' => $name ?? 'OpenEMR'
        ];
        return $data;
    }

    private function getJoinLink()
    {
        // the index-portal will redirect the person to login before completing the action
        return $this->publicPathFQDN . "index-portal.php";
    }

    private function sendMessageToPatient($htmlMsg, $plainMsg, $patient, $joinLink, $messageId)
    {
        // TODO: @adunsulag need to check to see if the SMTP notifications are configured.  If they are not we need to
        // skip over the email notifications.
        if (!$this->config->isEmailNotificationsConfigured()) {
            (new SystemLogger())->info(
                self::class
                . "->sendMessageToPatient() skipping email notification as email notifications are not configured",
                ['pid' => $patient['pid'], 'messageId' => $messageId]
            );
            return;
        }
        $email_subject = xl('Join Telehealth Session');
        $email_sender = $this->config->getPatientReminderName();

        $pt_name = $patient['fname'] . ' ' . $patient['lname'];
        $pt_email = $patient['email'];

        $event = new TelehealthNotificationSendEvent();
        $event->setMessageId($messageId);
        $event->setPatient($patient);
        $event->setSubject($email_subject);
        $event->setJoinLink($joinLink);
        $event->setFrom($email_sender, $email_sender);
        $event->addSendToDestination($pt_email, $pt_name);
        $event->addReplyToDestination($email_sender, $email_sender);
        $event->setTextBody($plainMsg);
        $event->setHTMLBody($htmlMsg);
        $resultEvent = $this->dispatcher->dispatch($event, TelehealthNotificationSendEvent::EVENT_HANDLE);

        $throwExceptions = true;
        $mail = new MyMailer($throwExceptions);

        foreach ($resultEvent->getReplyToDestinations() as $address) {
            if ($address->getType() == NotificationSendAddress::TYPE_EMAIL) {
                $mail->addReplyTo($address->getDestination(), $address->getName());
            }
        }

        foreach ($resultEvent->getSendToDestinations() as $address) {
            if ($address->getType() == NotificationSendAddress::TYPE_EMAIL) {
                $mail->AddAddress($address->getDestination(), $address->getName());
            }
        }

        $sender = $resultEvent->getFrom();
        $mail->setFrom($sender->getDestination(), $sender->getName());

        $mail->Subject = $resultEvent->getSubject();
        $mail->AltBody = $resultEvent->getTextBody();
        $htmlBody = $resultEvent->getHTMLBody();
        if (!empty($htmlBody)) {
            $mail->MsgHTML($htmlBody);
            $mail->IsHTML(true);
        }

        // the invitation is critical and participants can't join w/o it.  We will send any failure exceptions
        // up the chain to fail everything
        // if the email does not go out
        $mail->Send();
    }
}
