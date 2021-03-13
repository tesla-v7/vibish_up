<?php

require __DIR__ . '/vendor/autoload.php';

use App\Bee;

$bee = new Bee('login', 'password');
try{
    $data = $bee->up();
    foreach (glob('*.jpg') as $pic){
        unlink($pic);
    }
    echo 'Up: '. implode(', ', array_map(function ($item){return $item === 200 ? 'yes':'no';}, $data)) ."\n";
}catch (\Exception $e){
    echo 'error: '. $e->getMessage() ."\n";
}
