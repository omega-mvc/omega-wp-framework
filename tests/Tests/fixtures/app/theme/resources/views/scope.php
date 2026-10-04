<?php

$rendererLocals = ['view', 'viewPath', 'data', 'template'];
$visible = array_intersect($rendererLocals, array_keys(get_defined_vars()));

printf(
    'view=%s viewPath=%s data=%s this=%s locals=%s name=%s',
    isset($view) ? 'yes' : 'no',
    isset($viewPath) ? 'yes' : 'no',
    isset($data) ? 'yes' : 'no',
    isset($this) ? 'yes' : 'no',
    $visible === [] ? 'none' : implode(',', $visible),
    isset($name) && is_scalar($name) ? (string) $name : ''
);
