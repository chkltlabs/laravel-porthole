<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Support\ConfigExample;

it('copies the config example when the application does not have one', function () {
    $directory = sys_get_temp_dir().'/porthole-config-'.uniqid();
    mkdir($directory);
    $source = $directory.'/source.php';
    $target = $directory.'/porthole.php';
    file_put_contents($source, '<?php return [];');

    expect(ConfigExample::publishIfMissing($source, $target))->toBeTrue()
        ->and(is_file($target))->toBeTrue()
        ->and(ConfigExample::publishIfMissing($source, $target))->toBeFalse();

    unlink($source);
    unlink($target);
    rmdir($directory);
});

it('does not copy the config example when the destination directory is missing', function () {
    $source = sys_get_temp_dir().'/porthole-source-'.uniqid().'.php';
    file_put_contents($source, '<?php return [];');

    expect(ConfigExample::publishIfMissing($source, '/missing/porthole/config/porthole.php'))->toBeFalse();

    unlink($source);
});
