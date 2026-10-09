<?php
    include("includes/db_helper.php");
    require_once("includes/lb_helper.php");
    require_once("includes/policy_page.php");
    lf_policy_page('Politica de Privacidade', isset($settings_details['app_privacy_policy']) ? $settings_details['app_privacy_policy'] : '');
