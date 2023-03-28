<?php

/**
 * Payer Mix Report Help.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    stephen waite
 * @copyright Copyright (c) 2023 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\Header;

require_once("../../interface/globals.php");
?>
<!DOCTYPE html>
<html>
    <head>
    <?php Header::setupHeader();?>
    <title><?php echo xlt("Payer Mix Report Help");?></title>
    <style>
        .oe-help-add-info{
            padding:15px;
            border:6px solid;
            font-style: italic;
        }
    </style>
    </head>
    <body>
        <div class="container oe-help-container">
            <div>
                <h2 class="text-center"><a name='entire_doc'><?php echo xlt("Payer Mix Report Help");?></a></h2>
            </div>
            <div class= "row">
                <div class="col-sm-12">
                    <p><?php echo xlt("Report consists of charges for the time period and payments entered during the time period");?>.</p>                    
                </div>
            </div>
            
        </div><!--end of container div-->
        <script>
           $('.show_hide').click(function() {
                var elementTitle = $(this).prop('title');
                var hideTitle = '<?php echo xla('Click to Hide'); ?>';
                var showTitle = '<?php echo xla('Click to Show'); ?>';
                //$('.hideaway').toggle('1000');
                $(this).parent().parent().closest('div').children('.hideaway').toggle('1000');
                if (elementTitle == hideTitle) {
                    elementTitle = showTitle;
                    $(this).toggleClass('fa-eye-slash fa-eye');
                } else if (elementTitle == showTitle) {
                    elementTitle = hideTitle;
                    $(this).toggleClass('fa-eye fa-eye-slash');
                }
                $(this).prop('title', elementTitle);
            });
        </script>
    </body>
</html>
