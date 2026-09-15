<?php
return [
    'app_name' => 'MeliGil CRM',
    'timezone' => 'America/Mexico_City',
    'base_path' => '/meligil',
    'db' => [
        'host' => 'localhost',
        'name' => 'crmcamar_meligil',
        'user' => 'crmcamar_meligiluser',
        'password' => 'CAMBIAR_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'firebase_crm_url' => 'https://us-central1-meligilbot.cloudfunctions.net/crmSendMeligil',
    'firebase_admin_key' => 'MISMO_VALOR_QUE_WEB_ADMIN_KEY_MELIGILBOT',
    'marketing_template_name' => 'marketing_meligil_texto',
    'marketing_template_language' => 'es_MX',
];
