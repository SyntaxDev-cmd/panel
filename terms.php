<?php
    include("includes/db_helper.php");
    require_once("includes/lb_helper.php");
    require_once("includes/policy_page.php");
    lf_policy_page('Termos de Uso', isset($settings_details['app_terms']) ? $settings_details['app_terms'] : '');
