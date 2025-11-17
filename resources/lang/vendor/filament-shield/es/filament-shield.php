<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Columns
    |--------------------------------------------------------------------------
    */

    'column.name' => 'Nombre',
    'column.guard_name' => 'Guardia',
    'column.roles' => 'Roles',
    'column.permissions' => 'Permisos',
    'column.updated_at' => 'Actualizado el',

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */

    'field.name' => 'Nombre',
    'field.guard_name' => 'Guardia',
    'field.permissions' => 'Permisos',
    'field.select_all.name' => 'Seleccionar Todos',
    'field.select_all.message' => 'Habilita/Deshabilita todos los permisos para este rol',

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    // Changed the group name to place Roles into the Administración section
    'nav.group' => 'Administración',
    'nav.role.label' => 'Roles',
    'nav.role.icon' => 'heroicon-o-shield-check',
    'resource.label.role' => 'Rol',
    'resource.label.roles' => 'Roles',

    /*
    |--------------------------------------------------------------------------
    | Section & Tabs
    |--------------------------------------------------------------------------
    */

    'section' => 'Entidades',
    'resources' => 'Recursos',
    'widgets' => 'Widgets',
    'pages' => 'Páginas',
    'custom' => 'Permisos personalizados',

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    'forbidden' => 'Usted no tiene permiso de acceso',

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions' Labels
    |--------------------------------------------------------------------------
    */

    'resource_permission_prefixes_labels' => [
        'view' => 'Ver Detalle',
        'view_any' => 'Ver Listado',
        'create' => 'Crear Nuevo',
        'update' => 'Modificar',
        'delete' => 'Anular',
        'delete_any' => 'Anular Múltiples',
        'force_delete' => 'Eliminar Permanentemente',
        'force_delete_any' => 'Eliminar Múltiples Permanentemente',
        'restore' => 'Recuperar',
        'reorder' => 'Reorganizar',
        'restore_any' => 'Recuperar Múltiples',
        'replicate' => 'Duplicar',
    ],
];
