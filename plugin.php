<?php
/*
plugin_name: Djebel Contact Log CSV
plugin_uri: https://djebel.com/plugins/djebel-contact-log-csv
description: Logs djebel-contact submissions to a dated CSV file. Listens on app.plugin.contact.message_processed.
version: 1.0.0
load_priority: 20
tags: contact, csv, log, storage
stable_version: 1.0.0
min_php_ver: 5.6
min_dj_app_ver: 1.0.0
tested_with_dj_app_ver: 1.0.0
author_name: Svetoslav Marinov (Slavi)
company_name: Orbisius
author_uri: https://orbisius.com
text_domain: djebel-plugin-contact-log-csv
license: gpl2
*/

$obj = Djebel_Plugin_Contact_Log_Csv::getInstance();
Dj_App_Hooks::addAction('app.core.init', [ $obj, 'init' ]);

class Djebel_Plugin_Contact_Log_Csv
{
    public static function getInstance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }

    public function init()
    {
        // Persist each submission — listens on the contact plugin's hook
        Dj_App_Hooks::addAction('app.plugin.contact.message_processed', [ $this, 'saveToCsv' ]);
    }

    /**
     * Dated CSV file for today, under this plugin's own private data dir
     * @return string
     */
    public function getFile()
    {
        $params = [
            'plugin' => 'djebel-contact-log-csv',
        ];

        $dir = Dj_App_Util::getCorePrivateDataDir($params);
        $file = $dir . '/{YYYY}/{MM}/data_{YYYY}-{MM}-{DD}.csv';

        $replace_str = [
            'YYYY' => date('Y'),
            'MM' => date('m'),
            'DD' => date('d'),
        ];

        $file = Dj_App_Util::replaceTags($file, $replace_str);
        $file = Dj_App_Hooks::applyFilter('app.plugin.contact_log_csv.file', $file);

        return $file;
    }

    /**
     * Save a contact submission to CSV via hook
     * @param array $ctx
     */
    public function saveToCsv($ctx)
    {
        if (empty($ctx['data'])) {
            return;
        }

        $file = $this->getFile();
        $res = $this->writeCsv($file, $ctx['data']);

        if ($res->isError()) {
            error_log('djebel-contact-log-csv save failed: ' . $res->msg);
        }
    }

    /**
     * @param string $file
     * @param array $data
     * @return Dj_App_Result
     */
    public function writeCsv($file, $data = []) {
        $res_obj = new Dj_App_Result();
        $fp = null;

        try {
            Dj_App_Util::microtime( __METHOD__ );
            $dir = dirname($file);

            $res = Dj_App_File_Util::mkdir($dir);

            if (empty($res)) {
                throw new Dj_App_Exception('Failed to create directory ' . $dir);
            }

            $fp = fopen($file, 'ab');

            if (empty($fp)) {
                throw new Dj_App_File_Util_Exception("Couldn't create file", ['dir' => $dir]);
            }

            $fl_res = flock($fp, LOCK_EX);

            if (!$fl_res) {
                throw new Dj_App_File_Util_Exception("Couldn't lock file", ['file' => $file]);
            }

            $file_size = filesize($file);

            // new file so it needs a header
            if ($file_size < 100) {
                $header_cols = array_keys($data); // this is a row
                $header_cols = array_map('Dj_App_String_Util::formatStringId', $header_cols);
                $csv_res = fputcsv($fp, $header_cols, ",", '"', '\\');
            }

            // use csv; keep php 8.x happy and without warnings.
            $csv_res = fputcsv($fp, $data, ",", '"', '\\');

            if (empty($csv_res)) {
                throw new Dj_App_File_Util_Exception("Couldn't write to file", ['file' => $file]);
            }

            $res_obj->status(1);
        } catch (Exception $e) {
            $res_obj->msg = $e->getMessage();
        } finally {
            if (!empty($fp)) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            $res_obj->exec_time = Dj_App_Util::microtime( __METHOD__ );
        }

        return $res_obj;
    }
}
