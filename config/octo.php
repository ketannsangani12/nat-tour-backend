<?php

return [
    /*
     * Prefix used when generating OCTO API keys.
     * Full key format: {key_prefix}-{tenant_id}-{reseller}-{environment}
     */
    'key_prefix' => env('OCTO_KEY_PREFIX', 'native-american-tours'),

    /*
     * IP addresses and CIDR ranges permitted to call the OCTO API.
     * Leave empty to allow all IPs — token auth still applies regardless.
     * Add a comment per entry to document which partner/reseller owns the IP.
     */
    'allowed_ips' => [
        // Headout IP range (Given by Headout)
        '34.224.248.1',
        '88.212.17.76',
        '49.37.163.211',
        '34.224.113.75',
        '77.236.205.34',
        '213.91.199.83',
        '106.222.201.173',
        '109.81.88.185',
        '125.20.120.158',

        // Testers - NAT
        '202.71.27.237',
        '49.34.214.167',
    ],
];
