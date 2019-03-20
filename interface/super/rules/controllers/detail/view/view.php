<?php
 // Copyright (C) 2010-2011 Aron Racho <aron@mi-squred.com>
 //
 // This program is free software; you can redistribute it and/or
 // modify it under the terms of the GNU General Public License
 // as published by the Free Software Foundation; either version 2
 // of the License, or (at your option) any later version.

use OpenEMR\Core\Header;

Header::setupHeader();

$rule = $viewBean->rule ?>



<script type="text/javascript">
    var detail = new rule_detail( {editable: <?php echo $rule->isEditable() ? "true":"false"; ?>});
    detail.init();
</script>

<div class="panel panel-default">
    <div class="panel-heading"><?php echo out(xl('Rule Detail for ') . $rule->title); ?>
        <div class="panel-body">
            <a href="index.php?action=browse!list" class="iframe_medium css_button" onclick="top.restoreSession()"><span><?php echo out(xl('Back')); ?></span></a>
        </div>
    </div>

<div class="panel panel-default">
    <!--         -->
    <!-- summary -->
    <!--         -->
        <div class="panel-heading">
            <?php echo out(xl('Summary')); ?>
            <a href="index.php?action=edit!summary&id=<?php echo out($rule->id); ?>"
               class="action_link" id="edit_summary" onclick="top.restoreSession()">(<?php echo out(xl('edit')); ?>)</a>
        </div>
        <div class="panel-body">
            <p><?php echo out(xl($rule->title)); ?></b>
        (<?php echo implode_funcs(", ", $rule->getRuleTypeLabels(), array( 'xl', 'out' )); ?>)
           </p>
        <p><?php echo out(xl('Developer')); ?><b>:</b>&nbsp;<?php echo out($rule->developer); ?></p>
        <p><?php echo out(xl('Funding Source')); ?><b>:</b>&nbsp;<?php echo out($rule->funding_source); ?></p>
        <p><?php echo out(xl('Release')); ?><b>:</b>&nbsp;<?php echo out($rule->release); ?></p>
        <p><?php echo out(xl('Web Reference')); ?><b>:</b>&nbsp;<?php echo out($rule->web_ref); ?></p>
        </div>
</div>


    <!--                    -->
    <!-- reminder intervals -->
    <!--                    -->
    <?php $intervals = $rule->reminderIntervals; if ($intervals) { ?>
    <div class="container">
        <h3>
            <?php echo out(xl('Reminder intervals')); ?>
            <a href="index.php?action=edit!intervals&id=<?php echo $rule->id ?>" class="action_link" onclick="top.restoreSession()">(<?php echo out(xl('edit')); ?>)</a>
        </h3>

        <?php if ($intervals->getTypes()) {?>
        <table>
            <thead>
              <tr>
                <th><u><?php echo out(xl('Type')); ?></u></th>
                <th><u><?php echo out(xl('Detail')); ?></u></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($intervals->getTypes() as $type) {?>
                <tr>
                  <td><?php echo out(xl($type->lbl)); ?></td>
                  <td>
                    <?php echo out($intervals->displayDetails($type)); ?>
                  </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php } else { ?>
        <p><?php echo out(xl('None defined')); ?></p>
        <?php } ?>
    </div>
    <?php } ?>

    <!--                      -->
    <!-- rule filter criteria -->
    <!--                      -->
    <?php $filters = $rule->filters; if ($filters) { ?>
    <div class="section text">
        <p class="header"><?php echo out(xl('Demographics filter criteria')); ?> <a href="index.php?action=edit!add_criteria&id=<?php echo out($rule->id); ?>&criteriaType=filter" class="action_link" onclick="top.restoreSession()">(<?php echo out(xl('add')); ?>)</a></p>
        <p>
            <?php if ($filters->criteria) { ?>
<div class="container">
                <div class="row">
                    <div class="col-lg-3"><u><?php echo out(xl('Edit')); ?></u></div>
                    <div class="col-lg-3"><u><?php echo out(xl('Criteria')); ?></u></div>
                    <div class="col-lg-3"><u><?php echo out(xl('Characteristics')); ?></u></div>
                    <div class="col-lg-3"><u><?php echo out(xl('Requirements')); ?></u></div>
                </div>

                <?php foreach ($filters->criteria as $criteria) { ?>
                    <div class="row">
                        <div class="col-lg-3">
                            <a href="index.php?action=edit!filter&id=<?php echo out($rule->id); ?>&guid=<?php echo out($criteria->guid); ?>"
                               class="action_link" onclick="top.restoreSession()">
                                (<?php echo out(xl('edit')); ?>)
                            </a>
                            <a href="index.php?action=edit!delete_filter&id=<?php echo out($rule->id); ?>&guid=<?php echo out($criteria->guid); ?>" 
                               class="action_link" onclick="top.restoreSession()">
                                (<?php echo out(xl('delete')); ?>)
                            </a>
                        </div>
                        <div class="col-lg-3"><?php echo( out($criteria->getTitle()) ); ?></div>
                        <div class="col-lg-3"><?php echo( out($criteria->getCharacteristics()) ); ?></div>
                        <div class="col-lg-3"><?php echo( out($criteria->getRequirements()) ); ?></div>
                    </div>
        </div>
                <?php } ?>
            <?php } else { ?>
                <p><?php echo out(xl('None defined')); ?></p>
            <?php } ?>
        </p>
    </div>
    <?php } ?>
    
    <!--                      -->
    <!-- rule groups          -->
    <!--                      -->
    
    
    <div class="section text">
    <p class="header"><?php echo out(xl('Target/Action Groups')); ?></p>
    <?php $groupId = 0;
    foreach ($rule->groups as $group) {
        $groupId = $group->groupId; ?>
            <div class="group">
            <!--                      -->
            <!-- rule target criteria -->
            <!--                      -->
        
            <?php $targets = $group->ruleTargets; if ($targets) { ?>
        <div class="section text">
            <p class="header"><?php echo out(xl('Clinical targets')); ?> 
                <a href="index.php?action=edit!add_criteria&id=<?php echo out($rule->id); ?>&group_id=<?php echo out($group->groupId); ?>&criteriaType=target" class="action_link" onclick="top.restoreSession()">
                    (<?php echo out(xl('add')); ?>)
                </a>
            </p>
            <p>
                <?php if ($targets->criteria) { ?>
    
                    <div>
                        <span class="left_col">&nbsp;</span>
                        <span class="mid_col"><u><?php echo out(xl('Criteria')); ?></u></span>
                        <span class="mid_col"><u><?php echo out(xl('Characteristics')); ?></u></span>
                        <span class="end_col"><u><?php echo out(xl('Requirements')); ?></u></span>
                    </div>
    
                    <?php foreach ($targets->criteria as $criteria) { ?>
                        <div class="row">
                            <span class="left_col">
                                <a href="index.php?action=edit!target&id=<?php echo out($rule->id); ?>&guid=<?php echo out($criteria->guid); ?>"
                                   class="action_link" onclick="top.restoreSession()">
                                    (<?php echo out(xl('edit')); ?>)
                                </a>
                                <a href="index.php?action=edit!delete_target&id=<?php echo out($rule->id); ?>&guid=<?php echo out($criteria->guid); ?>"
                                   class="action_link" onclick="top.restoreSession()">
                                    (<?php echo out(xl('delete')); ?>)
                                </a>
                            </span>
                            <span class="mid_col"><?php echo( out($criteria->getTitle()) ); ?></span>
                            <span class="mid_col"><?php echo( out($criteria->getCharacteristics()) ); ?></span>
                            <span class="end_col">
                                    <?php echo( $criteria->getRequirements() ) ?>
                                    <?php echo is_null($criteria->getInterval()) ?  "" :
                                    " | " . out(xl('Interval')) . ": " . out($criteria->getInterval()); ?>
                            </span>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <p><?php echo out(xl('None defined')); ?></p>
                <?php } ?>
    
            </p>
        </div>
            <?php } ?>
    
            <!--              -->
            <!-- rule actions -->
            <!--              -->
            <?php $actions = $group->ruleActions; if ($actions) { ?>
        <div class="section text">
            <p class="header"><?php echo out(xl('Actions')); ?>
                <a href="index.php?action=edit!add_action&id=<?php echo out($rule->id); ?>&group_id=<?php echo out($group->groupId);?>" class="action_link" onclick="top.restoreSession()">
                    (<?php echo out(xl('add')); ?>)
                </a>
            </p>
            <p>
                <?php if ($actions->actions) { ?>
                    <div>
                        <span class="left_col">&nbsp;</span>
                        <span class="end_col"><u><?php echo out(xl('Category/Title')); ?></u></span>
                    </div>
    
                    <div>
                    <?php foreach ($actions->actions as $action) { ?>
                        <span class="left_col">
                            <a href="index.php?action=edit!action&id=<?php echo out($rule->id); ?>&guid=<?php echo out($action->guid); ?>"
                               class="action_link" onclick="top.restoreSession()">
                                (<?php echo out(xl('edit')); ?>)</a>
                            <a href="index.php?action=edit!delete_action&id=<?php echo out($rule->id); ?>&guid=<?php echo out($action->guid); ?>"
                               class="action_link" onclick="top.restoreSession()">
                                (<?php echo out(xl('delete')); ?>)</a>
                        </span>
                        <span class="end_col"><?php echo out($action->getTitle()); ?></span>
                    <?php } ?>
                    </div>
                <?php } else { ?>
                    <p><?php echo out(xl('None defined')); ?></p>
                <?php } ?>
            </p>
        </div>
            <?php } ?>
            </div>
        <?php
    } // iteration over groups ?>
        <div class="group">
            <?php $nextGroupId = $groupId + 1; ?>
            <div class="section text">
                <p class="header"><?php echo out(xl('Clinical targets')); ?> 
                    <a href="index.php?action=edit!add_criteria&id=<?php echo out($rule->id); ?>&group_id=<?php echo $nextGroupId; ?>&criteriaType=target" class="action_link" onclick="top.restoreSession()">
                        (<?php echo out(xl('add')); ?>)
                    </a>
                </p>
            </div>
            <div class="section text">
                <p class="header"><?php echo out(xl('Actions')); ?>
                    <a href="index.php?action=edit!add_action&id=<?php echo out($rule->id); ?>&group_id=<?php echo $nextGroupId; ?>" class="action_link" onclick="top.restoreSession()">
                        (<?php echo out(xl('add')); ?>)
                    </a>
                </p>
            </div>
        </div>
    
    </div>
</div>
