<?php

/**
 * Interface that provides tracking information for a claim batch
 *
 * The back-end ajax that goes with this datatables implementation is
 * located in library/ajax/billing_tracker_ajax.php
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Ken Chapple <ken@mi-squared.com>
 * @copyright Copyright (c) 2021 Ken Chapple <ken@mi-squared.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(__DIR__ . "/../globals.php");

use OpenEMR\Common\{
    Acl\AccessDeniedHelper,
    Acl\AclMain,
    Csrf\CsrfUtils,
    Session\SessionWrapperFactory,
    Twig\TwigContainer
};
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;

require_once OEGlobalsBag::getInstance()->getSrcDir() . "/options.inc.php";

//ensure user has proper access
if (!AclMain::aclCheckCore('acct', 'eob', '', 'write') && !AclMain::aclCheckCore('acct', 'bill', '', 'write')) {
    AccessDeniedHelper::denyWithTemplate("ACL check failed for acct/eob or acct/bill: Billing Manager", xl("Billing Manager"));
}

$session = SessionWrapperFactory::getInstance()->getActiveSession();
?>
<html>
<head>
    <?php Header::setupHeader(['datatables', 'datatables-colreorder', 'datatables-dt', 'datatables-bs']); ?>
    <title><?php echo xlt("Claim File Tracker"); ?></title>
    <style>
        table.dataTable td.details-control {
            cursor: pointer;
        }
    </style>
    <script type="text/javascript">
        $(document).ready(function() {
            const serverUrl = "<?php echo OEGlobalsBag::getInstance()->getWebRoot(); ?>/library/ajax/billing_tracker_ajax.php?csrf_token_form=" + <?php echo js_url(CsrfUtils::collectCsrfToken(session: $session)); ?>;
            // The control that opens a row's claims and messages. An icon element, not a glyph
            // written from CSS by font name: that stops showing when the icon font's name changes.
            const detailsToggle = '<button type="button" class="btn btn-link p-0 details-toggle" aria-expanded="false" title="'
                + jsAttr(<?php echo xlj('Show or hide the claims and messages'); ?>) + '">'
                + '<i class="fa fa-lg fa-caret-square-right" aria-hidden="true"></i></button>';
            const oTable = $('#billing-tracker-table').DataTable({
                "processing": true,
                // next 2 lines invoke server side processing
                "ajax": {
                    "type" : "GET",
                    "url" : serverUrl,
                    "dataSrc": function (json) {
                        return json.data;
                    }
                },
                "columns": [
                    {
                        "class": 'details-control',
                        "orderable": false,
                        "data": null,
                        "defaultContent": detailsToggle
                    },
                    {
                        "data": "status",
                        "render": function(data, type, row, meta) {
                            // Format the status with a nice looking badge.
                            // Compare against the raw enum (`row.status`) but
                            // display the translated label (`row.status_label`).
                            if (type !== 'display') {
                                return data;
                            }
                            const variants = { success: 'success', waiting: 'info' };
                            const variant = variants[data] || 'warning';
                            const label = row.status_label || data;
                            return `<span class="badge badge-${variant}">${jsText(label)}</span>`;
                        }
                    },
                    { "data": "x12_partner_name" },
                    {
                        "data": "x12_filename",
                        "render": function(data, type, row, meta) {
                            // Build the URL so the user can download the claim batch file
                            if (type === 'display') {
                                const params = new URLSearchParams({
                                    csrf_token_form: <?php echo js_escape(CsrfUtils::collectCsrfToken(session: $session)); ?>,
                                    key: data,
                                    partner: row.x12_partner_id
                                });
                                const url = '<?php echo OEGlobalsBag::getInstance()->getWebRoot(); ?>/interface/billing/get_claim_file.php?' + params;
                                data = '<a href="' + jsAttr(url) + '">' + jsText(data) + '</a>';
                            }

                            return data;
                        }
                    },
                    {
                        "data": "created_at",
                        "render": function(data, type, row, meta) {
                            // Build the URL so the user can download the claim batch file
                            if (type === 'display') {
                                data = '<td>' + window.top.oeFormatters.I18NDateFormat(data) + '</td>';
                            }

                            return data;
                        }
                    },
                    {
                        "data": "updated_at",
                        "render": function(data, type, row, meta) {
                            // Build the URL so the user can download the claim batch file
                            if (type === 'display') {
                                data = '<td>' + window.top.oeFormatters.I18NDateFormat(data) + '</td>';
                            }

                            return data;
                        }
                    },
                ],
                "order": [[4, 'desc']] // Order by 'Date Created' with newest first
            });

            oTable.on('preXhr.dt', function (e, settings, data) {
                console.log("before ajax call");
                top.restoreSession();
            });

            /* Formatting function for row details - modify as you need */
            function format (d) {
                // `d` is the original data object for the row
                // First output any messages from the SFTP
                let output = '';
                if (d.messages !== null) {
                    d.messages.forEach(message => {
                        output += '<div class="alert alert-info">' + jsText(message) + '</div>';
                    });
                }

                // Now output the claims in this batch
                output += '<table class="table" cellpadding="5" cellspacing="0" border="0" style="padding-left:50px;">';
                output +=
                    '<thead>' +
                        '<tr>' +
                            '<th>' + jsText(<?php echo xlj('Patient ID'); ?>) + '</th>' +
                            '<th>' + jsText(<?php echo xlj('Encounter ID'); ?>) + '</th>' +
                            '<th>' + jsText(<?php echo xlj('Payor ID'); ?>) + '</th>' +
                        '</tr>' +
                    '</thead>';
                output += '<tbody>';
                d.claims.forEach(claim => {
                    output +=
                        '<tr>' +
                            '<td>' + jsText(claim.pid) + '</td>' +
                            '<td>' + jsText(claim.encounter) + '</td>' +
                            '<td>' + jsText(claim.payor_id) + '</td>' +
                        '</tr>';
                });
                output += '</tbody>';

                return output;
            }

            // Add event listener for opening and closing details
            $('#billing-tracker-table tbody').on('click', 'td.details-control', function () {
                var tr = $(this).parents('tr');
                var row = oTable.row( tr );
                var open = !row.child.isShown();

                if (open) {
                    // Open this row
                    row.child( format(row.data()) ).show();
                } else {
                    // This row is already open - close it
                    row.child.hide();
                }
                tr.toggleClass('shown', open);
                $(this).find('.details-toggle').attr('aria-expanded', open ? 'true' : 'false')
                    .find('i').toggleClass('fa-caret-square-right', !open).toggleClass('fa-caret-square-down', open);
            } );
        });
    </script>
</head>
<body>
    <div id="container_div" class="mt-3">
         <div class="w-100 p-4">

             <table id="billing-tracker-table">
                 <thead>
                 <tr>
                     <th>&nbsp;</th>
                     <th><?php echo xlt('Status') ?></th>
                     <th><?php echo xlt('X-12 Partner') ?></th>
                     <th><?php echo xlt('File') ?></th>
                     <th><?php echo xlt('Date Created') ?></th>
                     <th><?php echo xlt('Date Updated') ?></th>
                 </tr>
                 </thead>
                 <tfoot>
                 <tr>
                     <th>&nbsp;</th>
                     <th><?php echo xlt('Status') ?></th>
                     <th><?php echo xlt('X-12 Partner') ?></th>
                     <th><?php echo xlt('File') ?></th>
                     <th><?php echo xlt('Date Created') ?></th>
                     <th><?php echo xlt('Date Updated') ?></th>
                 </tr>
                 </tfoot>
             </table>
        </div>
    </div>
</body>
</html>
