<?php
/**
 *
 * @author giggsey
 * @package Temp Monitor
 */

date_default_timezone_set('Europe/London');

chdir(__DIR__);


$config = json_decode(file_get_contents('config.json'), true);

$temps = file_exists('public/temp.json') ? json_decode(file_get_contents('public/temp.json'), true) : array();

foreach ($config as $name => $data) {

    echo "Processing '{$name}'\n";

    $sensor = $data['sensor'];

    $temperature = null;

    foreach (array('', '10','11','12') as $f) {
        $oneWireLocation = $sensor . '/temperature' . $f;
        $temp = floatval(trim(file_get_contents('/mnt/1wire/' . $oneWireLocation)));

        echo "{$oneWireLocation} reports '{$temp}'\n";

        if ($temp <= 60) {
            $temperature = $temp;
            // Temp seems okay
            break;
        }
    }

    if ($temperature === null) {
        echo "{$name} is too hot!\n";
        continue;
    }

    $rrdPath = 'rrd/' . $name . '.rrd';

    if (rrd_info($rrdPath) === false) {
        // RRD does not exist, create
        rrd_create(
            $rrdPath,
            array(
                "--step",
                "60",
                "DS:temperature:GAUGE:600:U:U",
                "RRA:AVERAGE:0.5:1:10080",
                "RRA:AVERAGE:0.5:5:4032",
                "RRA:AVERAGE:0.5:30:1344",
                "RRA:AVERAGE:0.5:120:21900",
                "RRA:MIN:0.5:1:10080",
                "RRA:MIN:0.5:5:4032",
                "RRA:MIN:0.5:30:1344",
                "RRA:MIN:0.5:120:21900",
                "RRA:MAX:0.5:1:10080",
                "RRA:MAX:0.5:5:4032",
                "RRA:MAX:0.5:30:1344",
                "RRA:MAX:0.5:120:21900"
            )
        );
    }

    echo "Updating {$rrdPath} to add '{$temperature}'\n";
    // Update RRD file
    rrd_update($rrdPath, array('N:' . $temperature));

    $min = array_key_exists('min', $data) ? $data['min'] : null;
    $max = array_key_exists('max', $data) ? $data['max'] : null;

    $temps[$name] = array('temp' => $temperature, 'updated' => time(), 'min' => $min, 'max' => $max);
}

ksort($temps);

file_put_contents('public/temp.json', json_encode($temps));

foreach ($config as $name => $data) {
    $rrdPath = 'rrd/' . $name . '.rrd';
    // Create graphs
    create_graph($name . '_hour', $rrdPath, $name, 3600);
    create_graph($name . '_day', $rrdPath, $name, 3600 * 24);
    create_graph($name . '_3day', $rrdPath, $name, 3600 * 24 * 3);
    create_graph($name . '_week', $rrdPath, $name, 3600 * 24 * 7);
    create_graph($name . '_month', $rrdPath, $name, 3600 * 24 * 30);
    create_graph($name . '_year', $rrdPath, $name, 3600 * 24 * 365);
}

function create_graph($output, $rrdPath, $name, $seconds)
{
    rrd_graph(
        'public/graphs/' . $output . '.png',
        array(
            '-h 80',
            '-w 600',
            '-s -' . $seconds,
            '-e now',
            '-t ' . $name,
            '--lazy',
            '-v °C',
            '--slope-mode',
            'DEF:temp=' . $rrdPath . ':temperature:AVERAGE',
            'DEF:min=' . $rrdPath . ':temperature:MIN',
            'DEF:max=' . $rrdPath . ':temperature:MAX',
            'LINE1:temp#0000FF:Temperature',
            'GPRINT:temp:AVERAGE:Avg\\: %6.1lf',
            'GPRINT:temp:MAX:Max\\: %6.1lf',
            'GPRINT:temp:MIN:Min\\: %6.1lf',
            'GPRINT:temp:LAST:Current\\: %6.1lf\\n'
        )
    );
}

/* EOF */