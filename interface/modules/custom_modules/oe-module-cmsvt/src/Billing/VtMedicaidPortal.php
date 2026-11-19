<?php

/**
 * Uploads a claim file to Vermont Medicaid through its web portal.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Billing;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use OpenEMR\Billing\BillingProcessor\X12RemoteTransportInterface;
use OpenEMR\Billing\BillingProcessor\X12RemoteTransportResult;

/**
 * Vermont Medicaid (Gainwell) takes claim files only through the Upload Files page of
 * vtmedicaid.com, not over SFTP. This signs in as a person does (the portal's forms are
 * plain posts: no script, token or one-time code), posts the file and reads the tracking
 * number off the confirmation page.
 *
 * "Uploaded successfully" means the portal has the file, not that it accepted the claims:
 * its acknowledgment arrives on the portal's download page later, as with an upload by hand.
 */
final readonly class VtMedicaidPortal implements X12RemoteTransportInterface
{
    /** The X12 partner's SFTP host that marks it as Vermont Medicaid. */
    public const HOST = 'vtmedicaid.com';

    public const BASE_URI = 'https://www.vtmedicaid.com';

    private const LOGON_PATH = '/secure/logon.do';
    private const UPLOAD_FORM_PATH = '/secure/upload.do';
    private const UPLOAD_PATH = '/secure/translator.do';

    /** The upload page's form; the logon page comes back instead when the sign-in failed. */
    private const UPLOAD_FORM_MARK = 'name="WebUploadForm"';
    private const CONFIRMATION_MARK = 'Uploaded successfully';

    public const CHECK_FIRST = 'Look for it on the portal before sending this file again.';

    /**
     * @param ClientInterface $http with BASE_URI as its base URI
     */
    public function __construct(private ClientInterface $http, private string $logonId, private string $password)
    {
    }

    public static function isPortalHost(string $sftpHost): bool
    {
        $host = strtolower(trim($sftpHost));

        return $host === self::HOST || str_ends_with($host, '.' . self::HOST);
    }

    /**
     * Sign in and open the upload page without sending anything: tells whether the
     * account and the portal's pages are as this class expects.
     */
    public function check(): X12RemoteTransportResult
    {
        return $this->signIn(new CookieJar()) ?? X12RemoteTransportResult::delivered('Signed in; the Upload Files page is there.');
    }

    public function send(string $filename, string $contents): X12RemoteTransportResult
    {
        $cookies = new CookieJar();
        $failure = $this->signIn($cookies);
        if ($failure !== null) {
            return $failure;
        }

        try {
            $response = $this->http->request('POST', self::UPLOAD_PATH, [
                'cookies' => $cookies,
                'multipart' => [
                    ['name' => 'screen_id', 'contents' => 'Upload'],
                    ['name' => 'directory', 'contents' => ''],
                    ['name' => 'dispopts', 'contents' => 'html, gif, jpg, other;;*'],
                    ['name' => 'userfile1', 'contents' => $contents, 'filename' => basename($filename)],
                    ['name' => 'action', 'contents' => 'Upload Files'],
                    ['name' => 'op-upload', 'contents' => ''],
                ],
            ]);
            $page = (string) $response->getBody();
        } catch (GuzzleException) {
            // The file may or may not have arrived.
            return X12RemoteTransportResult::uploadFailed('The connection to the Vermont Medicaid portal failed during the upload. ' . self::CHECK_FIRST);
        }
        if (!str_contains($page, self::CONFIRMATION_MARK)) {
            return X12RemoteTransportResult::uploadFailed('The Vermont Medicaid portal did not confirm the upload. ' . self::CHECK_FIRST);
        }
        if (preg_match('/Tracking Number:\s*(\d+)/', strip_tags($page), $match) !== 1) {
            return X12RemoteTransportResult::delivered('Uploaded through the Vermont Medicaid portal; it gave no tracking number.');
        }

        return X12RemoteTransportResult::delivered('Uploaded through the Vermont Medicaid portal. Tracking number ' . $match[1] . '.');
    }

    /**
     * @return ?X12RemoteTransportResult why the upload page could not be reached; null when signed in
     */
    private function signIn(CookieJar $cookies): ?X12RemoteTransportResult
    {
        if ($this->logonId === '' || $this->password === '') {
            return X12RemoteTransportResult::loginFailed('The X12 partner has no login or password for the Vermont Medicaid portal (its SFTP Login and SFTP Password).');
        }
        try {
            $this->http->request('POST', self::LOGON_PATH, [
                'cookies' => $cookies,
                'form_params' => [
                    'screen_id' => 'logon.jsp',
                    'logon_id' => $this->logonId,
                    'password' => $this->password,
                    'action' => 'Log In',
                ],
            ]);
            $page = (string) $this->http->request('GET', self::UPLOAD_FORM_PATH, ['cookies' => $cookies])->getBody();
        } catch (GuzzleException) {
            return X12RemoteTransportResult::loginFailed('Could not reach the Vermont Medicaid portal. Nothing was sent.');
        }
        if (!str_contains($page, self::UPLOAD_FORM_MARK)) {
            return X12RemoteTransportResult::loginFailed('The Vermont Medicaid portal did not show its Upload Files page after signing in: the login or password is wrong, or the portal has changed. Nothing was sent.');
        }

        return null;
    }
}
