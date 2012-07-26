<?php
define('SEPARATOR', "\t");
define('TIME_STR', date('Y-m-d', time()));
define('DEFAULT_ENCODING', 'UTF-8');

$config = array(
    'hash_table_size' => 49999,
    'original_file'   => './data/original', 
    'inverted_index'  => './data/inverted_index',
    'index_dict'      => './data/index_dict',
);


$r_orig_fp  = fopen($config['original_file'], 'r');
$w_index_fp = fopen($config['inverted_index'] . '.' .TIME_STR, 'w');
$w_dict_fp  = fopen($config['index_dict'] . '.' .TIME_STR, 'w');

if (!($r_orig_fp && $w_index_fp && $w_dict_fp))
{
    echo "Open file has something wrong, Please check it out.\n";
    exit();
}

$prefix_map = array();

$dict_id = 0;
while (! feof($r_orig_fp))
{
    $line = fgets($r_orig_fp, 2048);
    $line = str_replace(array("\n", "\r",), '', $line);

    $str_len = mb_strlen($line, DEFAULT_ENCODING);
    if ($str_len <= 0)
    {
        continue;
    }

    $exp_data = explode(SEPARATOR, $line);
    if (! (3 == count($exp_data)))
    {
        continue;
    }
    //print_r($exp_data);

    $dict_id++;
    $query = $exp_data[0];
    $dict = array(
        'dict_id' => $dict_id,
        'query'   => $query,
        'hot'     => $exp_data[1],
        'brief'   => $exp_data[2],
    );
    //print_r($dict);

    $index  = array();
    $prefix = '';

    $query_len = mb_strlen($query, DEFAULT_ENCODING);
    for ($i = 1; $i <= $query_len; $i++)
    {
        $prefix = mb_substr($query, 0, $i, DEFAULT_ENCODING);
        //echo $prefix . "\n";
        if (isset($prefix_map[$prefix]))
        {
            continue;
        }
        else
        {
            $prefix_map[$prefix] = 1;
        }

        $t_fp = fopen($config['original_file'], 'r');
        if (! $t_fp)
        {
            echo "Can NOT open file:{$config['original_file']}\n"; 
            exit();
        }
        
        $index_data = array(
            'prefix' => $prefix,
            'index'  => 0,
            'dict_ids' => array(),  
        );
        $sub_dict_id = 0;

        while (! feof($t_fp))
        {
            $cmp_line = fgets($t_fp, 2048);
            if (! strlen($cmp_line))
            {
                continue; 
            }
            
            $sub_dict_id++;

            $pos = mb_strpos($cmp_line, $prefix, 0, DEFAULT_ENCODING);
            if (false === $pos || $pos > 0)
            {
                continue;
            }

            $sub_exp_data = explode(SEPARATOR, $cmp_line);
            $hot = $sub_exp_data[1];

            $index = 0;
            $cmd = './hash ' . $prefix . ' ' . $config['hash_table_size'];
            $cli_ret = exec($cmd, $output);
            $output = NULL;
            $index = $cli_ret + 0;
            $index_data['index'] = $index;
            
            $index_data['dict_ids'][] = array(
                'dict_id' => $sub_dict_id,
                'hot'     => $hot,  
            );
        }
        //print_r($index_data);
        usort($index_data['dict_ids'], "cmp_function");

        write_index(&$w_index_fp, &$index_data);

        fclose($t_fp);
    }

    write_dict(&$w_dict_fp, &$dict);
}

fclose($w_dict_fp);
fclose($w_index_fp);
fclose($r_orig_fp);

$cmd = 'cp ' . $config['inverted_index'] . '.' . TIME_STR . ' ' . $config['inverted_index'];
exec($cmd);
$cmd = 'cp ' . $config['index_dict'] . '.' . TIME_STR . ' ' . $config['index_dict'];
exec($cmd);

function cmp_function($a, $b)
{
    return $b['hot'] - $a['hot'];
}

function write_index(&$fp, &$index_data)
{
    $log = $index_data['prefix'] . "\t" . $index_data['index'] . "\t";
    foreach ($index_data['dict_ids'] as $key => $info)
    {
        $log .= "{$info['dict_id']},";
    }
    $log .= "\n";
    fwrite($fp, $log, strlen($log));
}

function write_dict(&$fp, &$dict)
{
    $log = implode(SEPARATOR, $dict) . "\n";
    fwrite($fp, $log, strlen($log));
}