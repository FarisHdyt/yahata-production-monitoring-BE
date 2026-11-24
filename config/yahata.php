<?php

return [
    'machines' => [
        'BP-450', 'CBP-105', 'CBP-106', 'CBP-136', 'HBF-823', 'HBF-823 NEW',
        'HM-552', 'HM-676', 'NF-19B', 'NP-12', 'PF-860', 'YDP-136',
        'YH-1552', 'ZH-30200',
    ],

    'shifts' => [
        1 => ['key' => 'shift1', 'name' => 'Shift 1', 'start' => '06:00', 'end' => '15:00'],
        2 => ['key' => 'shift2', 'name' => 'Shift 2', 'start' => '15:00', 'end' => '23:00'],
    ],

    // is_downtime=false -> Running Production, tidak dihitung downtime
    'categories' => [
        ['code' => 1,  'name' => 'Running Production',                 'is_downtime' => false],
        ['code' => 2,  'name' => 'Dandori (lepas dan pasang tools)',   'is_downtime' => true],
        ['code' => 3,  'name' => 'Dandori (setting dan adjustment)',   'is_downtime' => true],
        ['code' => 4,  'name' => 'QC Check (inspection part)',         'is_downtime' => true],
        ['code' => 5,  'name' => 'Trouble Machine',                    'is_downtime' => true],
        ['code' => 6,  'name' => 'Trouble Tooling',                    'is_downtime' => true],
        ['code' => 7,  'name' => 'Ganti Tooling',                      'is_downtime' => true],
        ['code' => 8,  'name' => 'Ganti Material',                     'is_downtime' => true],
        ['code' => 9,  'name' => 'Mesin Stop',                         'is_downtime' => true],
        ['code' => 10, 'name' => 'Delay Material',                     'is_downtime' => true],
        ['code' => 11, 'name' => 'Delay Tooling',                      'is_downtime' => true],
        ['code' => 12, 'name' => 'Delay Job Order',                    'is_downtime' => true],
        ['code' => 13, 'name' => 'No Plan',                            'is_downtime' => true],
    ],
];