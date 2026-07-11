<?php

return [
    'seat-spy-hunter' => [
        'name' => 'Spy Hunter',
        'label' => 'seat-spy-hunter::sidebar.spy_hunter',
        'icon' => 'fas fa-user-secret',
        'route_segment' => 'seat-spy-hunter',
        'permission' => 'seat-spy-hunter.view',
        'entries' => [
            [
                'name' => 'Dashboard',
                'label' => 'seat-spy-hunter::sidebar.dashboard',
                'icon' => 'fas fa-chart-line',
                'route' => 'seat-spy-hunter.index',
                'permission' => 'seat-spy-hunter.view',
            ],
            [
                'name' => 'Caches',
                'label' => 'seat-spy-hunter::sidebar.caches',
                'icon' => 'fas fa-database',
                'route' => 'seat-spy-hunter.caches',
                'permission' => 'seat-spy-hunter.settings',
            ],
            [
                'name' => 'Help',
                'label' => 'seat-spy-hunter::sidebar.help',
                'icon' => 'fas fa-question-circle',
                'route' => 'seat-spy-hunter.help',
                'permission' => 'seat-spy-hunter.view',
            ],
            [
                'name' => 'Settings',
                'label' => 'seat-spy-hunter::sidebar.settings',
                'icon' => 'fas fa-cog',
                'route' => 'seat-spy-hunter.settings',
                'permission' => 'seat-spy-hunter.settings',
            ],
        ],
    ],
];
