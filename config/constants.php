<?php

/**
 * register global config to reuse
 * call with command config('constants.[name].[value]')
 */
/**
 * Define DOMAIN API
 */
define('MAIN_DOMAIN', env('MAIN_DOMAIN', 'https://api.his.devtest.vn/'));
define('API_MEDIA', env('API_MEDIA', 'https://media.his.devtest.vn'));
define('GATEWAY_PAYMENT_DOMAIN', env('GATEWAY_PAYMENT_DOMAIN', 'https://gw-payment.his.devtest.vn'));
define('PUBLIC_MEDIA_KEY', env('PUBLIC_MEDIA_KEY', 'AZUREAWSGCPCLOUDHISTESTMINIO'));
define('SECRET_MEDIA_KEY', env('SECRET_MEDIA_KEY', 'wJalrXUtnFEK7MDsENGbPxRfiCYHISTEST'));
define('CRM_TRAIN_API', MAIN_DOMAIN . 'crm/');
define('CRM_STAGING_API', CRM_TRAIN_API);
define('JWT_APP_TOKEN', env('JWT_APP_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJjYmMzODBmNi00OGFmLTQ4Y2ItODdmNS04MWViNmVjZGE3OGMifQ.eyJleHAiOjE2OTE1NTExMzQsImlhdCI6MTY5MTQ2NDczNSwianRpIjoiZTk4NmY4YjktZDJkNC00YjNiLWI0ZDgtMmE1YzBjNTQyOTJjIiwiaXNzIjoiaHR0cHM6Ly9hdXRoLmhpcy5kZXZ0ZXN0LnZuL2F1dGgvcmVhbG1zL0tJTSIsInN1YiI6IjAwZjFhZDc4LWQ5YzUtNDI0OS1hZmFjLTc1Y2MzZGM1ZDQ1MSIsInR5cCI6IkJlYXJlciIsImF6cCI6ImFkbWluLWNsaSIsInNlc3Npb25fc3RhdGUiOiJiODUwNDI4Yi02MmYyLTRjNzktYjJhNi1iZTE1NjA2NGNiOWMiLCJhY3IiOiIxIiwic2NvcGUiOiJlbWFpbCBwcm9maWxlIiwic2lkIjoiYjg1MDQyOGItNjJmMi00Yzc5LWIyYTYtYmUxNTYwNjRjYjljIiwiZW1haWxfdmVyaWZpZWQiOnRydWUsIm5hbWUiOiJBSSBBdXRvIEJvdCIsInByZWZlcnJlZF91c2VybmFtZSI6InJlY2VwdGlvbi50ZXN0QGFpcGFjaWZpYy52biIsImdpdmVuX25hbWUiOiJBSSIsImZhbWlseV9uYW1lIjoiQXV0byBCb3QiLCJlbWFpbCI6InJlY2VwdGlvbi50ZXN0QGFpcGFjaWZpYy52biJ9.kgeXdq0b673kKJLb_rycphbdv2hJ_YSLUsa6stnjDJo'));
define('CRM_APP_TOKEN', env('CRM_APP_TOKEN', 'eyJpdiI6IjgyVHBQMzRHd2h5YTdZZXdaZVgzVnc9PSIsInZhbHVlIjoiV2hscStITGl3dkN6UHFZSkZmemhnR0ZWQjYwZCt3Y1g2SU9lN3dMRDVuVT0iLCJtYWMiOiJkNGMxZmFmYzUwYzU1NGZhZTdhYzZjZTBjNzAyNTkzZWYxOWRjYTBhYWExNDhkMWNhM2U1OTQ2MWJmM2RkYWFiIn0='));
define('API_QUEUE_EXPORT_CUSTOMER_PAPER_WORK', MAIN_DOMAIN . 'queue-v2/pdf/customer-paper-work');
define('API_QUEUE_EXPORT_CUSTOMER_TREATMENT_PLANING', MAIN_DOMAIN . 'queue-v2/pdf/treatment-package-planing');
define('API_SEND_NOTIFICATION', MAIN_DOMAIN . 'notification/autoNotification');
define('API_CRM_GATEWAY_CREATE_HOT_DATA', MAIN_DOMAIN . 'crm/gateway/createHotData');
define('API_POS_WIDGET', MAIN_DOMAIN . 'pos/init?_renderer=widget');
define('API_SEND_NOTIFICATION_REFRESH_PAGE', MAIN_DOMAIN . 'notification/sendRefreshPage');
define('API_SEND_NOTIFICATION_REFRESH_PAGE_BY_USER', MAIN_DOMAIN . 'notification/sendRefreshPageByUser');
define('API_PROMOTION_CHECK_VOUCHER_CODE', MAIN_DOMAIN . 'promotion/client/checkVoucherCode');
define('DEFAULT_IMG_URL', 'https://media.kimdental.vn/images/default.jpg');
define('API_SYNC_PAYMENT_APPOINTMENT', MAIN_DOMAIN . 'gateway-crm/crm?op=SyncPaymentAppointment');
define('API_SYNC_EXPENDITURE', MAIN_DOMAIN . 'gateway-crm/crm?op=SyncExpenditure');
define('API_SEND_DEPOSIT_CUSTOMER', MAIN_DOMAIN . 'mail/ZNS/sendDepositCustomer');
define('API_SEND_EDIT_DEPOSIT_CUSTOMER', MAIN_DOMAIN . 'mail/ZNS/sendEditDepositCustomer');
define('API_PAYMENT_MPOS_EDC', GATEWAY_PAYMENT_DOMAIN . '/' . 'api/v1/mpos/edc-pos');
define('API_GET_SCHEDULE_MANAGER', MAIN_DOMAIN . 'hr/schedule/ScheduleManager?_act=listSchedule');
return [
    'api' => [
        'API_HR_GET_INFO_STAFF_BY_USER_ID' => MAIN_DOMAIN . 'hr/staff/profile?_act=simpleInfo&_renderer=module',
        'API_HR_GET_INFO_STAFF_BY_PHONE' => MAIN_DOMAIN . 'hr/staff/profile?_act=getStaffInfoByPhone&_renderer=module',
        'API_CRM_UPDATE_RELATIONSHIP_DEV' => CRM_TRAIN_API . '/gateway/syncCustomerRelationshipCallback',
        'API_CRM_UPDATE_RELATIONSHIP_STAGING' => CRM_STAGING_API . '/gateway/syncCustomerRelationshipCallback',
    ],
    'response' => [
        'checkCustomerIsStaff' => [
            'not' => 0,
            'isStaff' => 1,
            'isStaffRelationship' => 2
        ],
        'getTotalAmountAndLevel' => [
            'not' => 0,
            'amountGLD' => 10000000,
            'amountDIA' => 50000000,
            'amountPLA' => 100000000
        ],
    ],
    'messages' => [
        'error' => 1,
        'success' => 2,
        'warning' => 3
    ],
    'customer' => [
        'image' => [
            'type' => [
                'xray' => 1,
                'accept' => 2,
                'test_blood' => 3,
                'commit' => 4,
                'letter' => 5,
                'surgical_sequence' => 6
            ]
        ],
        'paper_work' => [
            'state' => [
                'deleted' => 0,
                'done' => 1,
                'new' => 2,
                'processing' => 3
            ]
        ]
    ],
    'orc' => [
        'api' => env('FPT_API'),
        'secretKey' => env('FPT_SECRET_KEY'),
    ],
    'service' => [
        'type' => [
            'C' => 'Niềng',
            'P' => 'Sứ',
            'I' => 'Implant',
            'T' => 'Tổng quát',
        ]
    ],
    'log' => [
        'telegram' => [
            'token'   => env('TELEGRAM_LOG_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_LOG_CHAT_ID'),
        ],
    ],
    'lucky_draw' => [
        1 => [
            'name' => 'Vòng quay may mắn',
            'type_id' => 1,
            'min_amount' => 10000000,
            'branch_ids' => [
                1, 2, 3, 4, 5, 6, 7, 12, 14, 15, 16, 18, 20, 21,
                24, 25, 26, 28, 29, 35, 36, 39, 40, 43, 45, 46,
                47, 48, 49, 50, 51, 52, 63, 67, 68, 70, 72, 73,
                74, 75, 76, 77, 78, 79, 80, 81, 82
            ] // HCM
        ],
        2 => [
            'name' => 'Xé túi mù',
            'type_id' => 2,
            'min_amount' => 10000000,
            'branch_ids' => [83]  // Cần Thơ
        ]
    ]
];
