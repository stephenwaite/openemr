<?php

/**
 * Tests the upload of a claim file through the Vermont Medicaid portal.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace {
    $cmsvtModuleSrc = dirname(__DIR__, 5) . '/interface/modules/custom_modules/oe-module-cmsvt/src/';
    if (!is_dir($cmsvtModuleSrc)) {
        throw new RuntimeException('oe-module-cmsvt source not found at ' . $cmsvtModuleSrc);
    }
    spl_autoload_register(static function (string $class) use ($cmsvtModuleSrc): void {
        $prefix = 'Cmsvt\\OpenEMR\\Modules\\Customizations\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = $cmsvtModuleSrc . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

namespace OpenEMR\Tests\Isolated\Modules\Cmsvt {

    use Cmsvt\OpenEMR\Modules\Customizations\Billing\ClaimOptions;
    use Cmsvt\OpenEMR\Modules\Customizations\Billing\VtMedicaidPortal;
    use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\ConnectException;
    use GuzzleHttp\Handler\MockHandler;
    use GuzzleHttp\HandlerStack;
    use GuzzleHttp\Middleware;
    use GuzzleHttp\Psr7\Request;
    use GuzzleHttp\Psr7\Response;
    use OpenEMR\Billing\BillingProcessor\X12RemoteTransportOutcome;
    use OpenEMR\Core\OEGlobalsBag;
    use OpenEMR\Events\Billing\X12RemoteTransportEvent;
    use PHPUnit\Framework\TestCase;
    use Psr\Http\Message\RequestInterface;

    final class VtMedicaidPortalTest extends TestCase
    {
        private const LANDING = '<html><a id="secureOptions">Secure Options</a><a href="/secure/upload.do">Upload Files</a></html>';
        private const UPLOAD_FORM = '<form method="post" enctype="multipart/form-data" action="/secure/translator.do" name="WebUploadForm"></form>';
        private const LOGON_FORM = '<form name="logonForm" method="post" action="/secure/logon.do"><input name="logon_id"/></form>';
        private const CONFIRMED = '<div class="container"><p>Uploaded successfully</p><p>Tracking <b>Number:</b> 48213377</p></div>';

        /** @var \ArrayObject<int, mixed> what the client sent, as Guzzle's history middleware records it */
        private \ArrayObject $sent;

        /**
         * A portal that answers with these, in order.
         *
         * @param list<Response|ConnectException> $answers
         */
        private function portal(array $answers, string $logonId = 'cmsvt01', string $password = 'secret'): VtMedicaidPortal
        {
            $sent = new \ArrayObject();
            $this->sent = $sent;
            $stack = HandlerStack::create(new MockHandler($answers));
            $stack->push(Middleware::history($sent));

            return new VtMedicaidPortal(new Client(['handler' => $stack, 'base_uri' => VtMedicaidPortal::BASE_URI]), $logonId, $password);
        }

        /**
         * @return list<string> "METHOD path" of each request made
         */
        private function requests(): array
        {
            $requests = [];
            foreach (array_keys($this->sent->getArrayCopy()) as $i) {
                $request = $this->request((int) $i);
                $requests[] = $request->getMethod() . ' ' . $request->getUri()->getPath();
            }

            return $requests;
        }

        private function request(int $i): RequestInterface
        {
            $transaction = $this->sent[$i] ?? null;
            $request = is_array($transaction) ? ($transaction['request'] ?? null) : null;
            $this->assertInstanceOf(RequestInterface::class, $request);

            return $request;
        }

        private static function dropped(): ConnectException
        {
            return new ConnectException('cURL error 28: Operation timed out (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://www.vtmedicaid.com/secure/logon.do', new Request('GET', '/'));
        }

        public function testSignsInUploadsTheFileAndReportsTheTrackingNumber(): void
        {
            $portal = $this->portal([new Response(200, [], self::LANDING), new Response(200, [], self::UPLOAD_FORM), new Response(200, [], self::CONFIRMED)]);

            $result = $portal->send('/edi/2026-11-18-180350-batch-p18.txt', 'ISA*00*~IEA*1~');

            $this->assertSame(X12RemoteTransportOutcome::Delivered, $result->outcome);
            $this->assertTrue($result->isDelivered());
            $this->assertSame(['Uploaded through the Vermont Medicaid portal. Tracking number 48213377.'], $result->messages);
            $this->assertSame(['POST /secure/logon.do', 'GET /secure/upload.do', 'POST /secure/translator.do'], $this->requests());

            $this->assertSame('www.vtmedicaid.com', $this->request(0)->getUri()->getHost());
            // Over HTTP/2: the portal drops an HTTP/1.1 connection after answering.
            $this->assertSame(['2.0', '2.0', '2.0'], array_map(fn (int $i): string => $this->request($i)->getProtocolVersion(), [0, 1, 2]));
            parse_str((string) $this->request(0)->getBody(), $logon);
            $this->assertSame(['screen_id' => 'logon.jsp', 'logon_id' => 'cmsvt01', 'password' => 'secret', 'action' => 'Log In'], $logon);

            $upload = (string) $this->request(2)->getBody();
            $this->assertStringContainsString('multipart/form-data', $this->request(2)->getHeaderLine('Content-Type'));
            $this->assertStringContainsString('name="userfile1"; filename="2026-11-18-180350-batch-p18.txt"', $upload);
            $this->assertStringContainsString('ISA*00*~IEA*1~', $upload);
            $this->assertMatchesRegularExpression('/name="screen_id".*\r\n\r\nUpload\r\n/sU', $upload);
            $this->assertStringContainsString('name="op-upload"', $upload);
        }

        public function testAConfirmationWithoutATrackingNumberIsStillDelivered(): void
        {
            $portal = $this->portal([new Response(200), new Response(200, [], self::UPLOAD_FORM), new Response(200, [], '<p>Uploaded successfully</p>')]);

            $result = $portal->send('batch.txt', 'ISA~');

            $this->assertTrue($result->isDelivered());
            $this->assertSame(['Uploaded through the Vermont Medicaid portal; it gave no tracking number.'], $result->messages);
        }

        public function testARefusedLoginSendsNothing(): void
        {
            // The portal answers a wrong password, and the upload page after it, with the logon form.
            $portal = $this->portal([new Response(200, [], self::LOGON_FORM), new Response(200, [], self::LOGON_FORM)]);

            $result = $portal->send('batch.txt', 'ISA~');

            $this->assertSame(X12RemoteTransportOutcome::LoginFailed, $result->outcome);
            $this->assertFalse($result->isDelivered());
            $this->assertStringContainsString('Nothing was sent.', $result->messages[0]);
            $this->assertSame(['POST /secure/logon.do', 'GET /secure/upload.do'], $this->requests());
        }

        public function testAPartnerWithoutALoginOrPasswordIsNotTried(): void
        {
            $this->assertSame(X12RemoteTransportOutcome::LoginFailed, $this->portal([], '', 'secret')->send('batch.txt', 'ISA~')->outcome);
            $this->assertSame([], $this->requests());
            $this->assertSame(X12RemoteTransportOutcome::LoginFailed, $this->portal([], 'cmsvt01', '')->check()->outcome);
            $this->assertSame([], $this->requests());
        }

        public function testAPortalThatCannotBeReachedSendsNothing(): void
        {
            $result = $this->portal([self::dropped()])->send('batch.txt', 'ISA~');

            $this->assertSame(X12RemoteTransportOutcome::LoginFailed, $result->outcome);
            $this->assertSame(
                ['Could not reach the Vermont Medicaid portal (cURL error 28: Operation timed out for https://www.vtmedicaid.com/secure/logon.do). Nothing was sent.'],
                $result->messages
            );
        }

        public function testAnUploadThePortalDoesNotConfirmIsAnErrorToLookAt(): void
        {
            $portal = $this->portal([new Response(200), new Response(200, [], self::UPLOAD_FORM), new Response(200, [], '<p>File rejected</p>')]);

            $result = $portal->send('batch.txt', 'ISA~');

            $this->assertSame(X12RemoteTransportOutcome::UploadFailed, $result->outcome);
            $this->assertSame(['The Vermont Medicaid portal did not confirm the upload (HTTP 200). ' . VtMedicaidPortal::CHECK_FIRST], $result->messages);
        }

        public function testAConnectionLostDuringTheUploadIsAnErrorToLookAt(): void
        {
            $portal = $this->portal([new Response(200), new Response(200, [], self::UPLOAD_FORM), self::dropped()]);

            $result = $portal->send('batch.txt', 'ISA~');

            $this->assertSame(X12RemoteTransportOutcome::UploadFailed, $result->outcome);
            $this->assertStringEndsWith(VtMedicaidPortal::CHECK_FIRST, $result->messages[0]);
        }

        public function testAServerErrorDuringTheUploadIsAnErrorToLookAt(): void
        {
            $portal = $this->portal([new Response(200), new Response(200, [], self::UPLOAD_FORM), new Response(500)]);

            $result = $portal->send('batch.txt', 'ISA~');

            $this->assertSame(X12RemoteTransportOutcome::UploadFailed, $result->outcome);
            $this->assertStringContainsString('(HTTP 500)', $result->messages[0]);
        }

        public function testAnErrorStatusWhileSigningInSaysWhichStepAndStatus(): void
        {
            // The sign-in itself refused.
            $result = $this->portal([new Response(403)])->check();
            $this->assertSame(X12RemoteTransportOutcome::LoginFailed, $result->outcome);
            $this->assertSame(['The Vermont Medicaid portal answered the sign-in with HTTP 403. Nothing was sent.'], $result->messages);
            $this->assertSame(['POST /secure/logon.do'], $this->requests());

            // Signed in or not, the upload page is refused.
            $result = $this->portal([new Response(200, [], self::LANDING), new Response(401)])->send('batch.txt', 'ISA~');
            $this->assertSame(X12RemoteTransportOutcome::LoginFailed, $result->outcome);
            $this->assertStringContainsString('after signing in (HTTP 401)', $result->messages[0]);
            $this->assertSame(['POST /secure/logon.do', 'GET /secure/upload.do'], $this->requests());
        }

        public function testTheCheckSignsInAndUploadsNothing(): void
        {
            $result = $this->portal([new Response(200, [], self::LANDING), new Response(200, [], self::UPLOAD_FORM)])->check();

            $this->assertTrue($result->isDelivered());
            $this->assertSame(['POST /secure/logon.do', 'GET /secure/upload.do'], $this->requests());
        }

        public function testOnlyVermontMedicaidsHostIsThePortal(): void
        {
            $this->assertTrue(VtMedicaidPortal::isPortalHost('vtmedicaid.com'));
            $this->assertTrue(VtMedicaidPortal::isPortalHost(' WWW.VTMedicaid.com '));
            $this->assertFalse(VtMedicaidPortal::isPortalHost('notvtmedicaid.com'));
            $this->assertFalse(VtMedicaidPortal::isPortalHost('moveit.bcbsvt.com'));
            $this->assertFalse(VtMedicaidPortal::isPortalHost(''));
        }

        public function testTheSiteSettingPutsVermontMedicaidOnThePortalAndNoOtherPartner(): void
        {
            /** @var \ArrayObject<int, string> $asked the hosts whose password was read */
            $asked = new \ArrayObject();
            $event = static fn (string $host): X12RemoteTransportEvent => new X12RemoteTransportEvent(
                18,
                'Gainwell Technology',
                $host,
                'cmsvt01',
                static function () use ($asked, $host): string {
                    $asked->append($host);
                    return 'secret';
                }
            );
            $on = new ClaimOptions(new OEGlobalsBag([CmsvtGlobals::CLAIM_VTMEDICAID_PORTAL => '1']));

            // Off, as on every site until it is turned on: SFTP as before, the password never read.
            $off = $event('vtmedicaid.com');
            (new ClaimOptions(new OEGlobalsBag([])))->applyRemoteTransport($off);
            $this->assertNull($off->getTransport());

            $other = $event('moveit.bcbsvt.com');
            $on->applyRemoteTransport($other);
            $this->assertNull($other->getTransport());
            $this->assertCount(0, $asked);

            $medicaid = $event('vtmedicaid.com');
            $on->applyRemoteTransport($medicaid);
            $this->assertInstanceOf(VtMedicaidPortal::class, $medicaid->getTransport());
            $this->assertSame(['vtmedicaid.com'], $asked->getArrayCopy());
            $this->assertSame([18, 'Gainwell Technology', 'cmsvt01'], [$medicaid->partnerId, $medicaid->partnerName, $medicaid->sftpLogin]);
        }
    }
}
