<?php

/**
 * WHMCS SDK Sample Addon Module
 *
 * An addon module allows you to add additional functionality to WHMCS. It
 * can provide both client and admin facing user interfaces, as well as
 * utilise hook functionality within WHMCS.
 *
 * This sample file demonstrates how an addon module for WHMCS should be
 * structured and exercises all supported functionality.
 *
 * Addon Modules are stored in the /modules/addons/ directory. The module
 * name you choose must be unique, and should be all lowercase, containing
 * only letters & numbers, always starting with a letter.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "addonmodule" and therefore all functions
 * begin "activation_adobe_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/addon-modules/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

/**
 * Require any libraries needed for the module to function.
 * require_once __DIR__ . '/path/to/library/loader.php';
 *
 * Also, perform any initialization required by the service's library.
 */

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define addon module configuration parameters.
 *
 * Includes a number of required system fields including name, description,
 * author, language and version.
 *
 * Also allows you to define any configuration parameters that should be
 * presented to the user when activating and configuring the module. These
 * values are then made available in all module function calls.
 *
 * Examples of each and their possible configuration parameters are provided in
 * the fields parameter below.
 *
 * @return array
 */
function activation_adobe_config()
{
    return [
        // Display name for your module
        'name' => 'Activation Adobe',
        // Description displayed within the admin interface
        'description' => 'Ce module permet d\'obtenir des CSV des comptes Adobes à activer.',
        // Module author name
        'author' => '<a href="https://linkedin.com/in/loanfrancois/" target="_blank">LoanF</a>',
        // Default language
        'language' => 'french',
        // Version number
        'version' => '1.0',
        'fields' => [
            'package_id' => [
                'FriendlyName' => 'ID du package',
                'Type' => 'text',
                'Size' => '25',
                'Description' => 'ID du package à activer',
            ],
        ]
    ];
}

/**
 * Activate.
 *
 * Called upon activation of the module for the first time.
 * Use this function to perform any database and schema modifications
 * required by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function activation_adobe_activate()
{
    // Create custom tables and schema required by your module
    try {
        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'activation Adobe Module Activated Successfully',
        ];
    } catch (\Exception $e) {
        return [
            // Supported values here include: success, error or info
            'status' => "error",
            'description' => 'Unable to create mod_activation_adobe : ' . $e->getMessage(),
        ];
    }
}

/**
 * Deactivate.
 *
 * Called upon deactivation of the module.
 * Use this function to perform any required cleanup of your module data.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function activation_adobe_deactivate()
{
    // Undo any database and schema modifications made by your module here
    try {
        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'Activation Adobe Module Deactivated Successfully',
        ];
    } catch (\Exception $e) {
        return [
            // Supported values here include: success, error or info
            'status' => "error",
            'description' => 'Unable to drop mod_activation_adobe : ' . $e->getMessage(),
        ];
    }
}

function activation_adobe_output($vars)
{
    $modulelink = $vars['modulelink'];

    $addonConfig = Capsule::table('tbladdonmodules')
            ->where('module', 'activation_adobe')
            ->where('setting', 'package_id')
            ->first();

    if (!$addonConfig) {
        echo 'Module non configuré';
        return;
    }

    // Récupérer toutes les vérifications avec les informations des utilisateurs
    $activations = Capsule::table('tblhosting')
        ->select('tblhosting.id as hosting_id', 'tblhosting.*', 'tblclients.*', 'mod_student_verification.*')
        ->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id')
        ->join('mod_student_verification', 'tblhosting.userid', '=', 'mod_student_verification.student_id')
        ->where('domainstatus', 'Pending')
        ->where('mod_student_verification.verified', true)
        ->where('packageid', $addonConfig->value)
        ->get();

    $activations = $activations->toArray();
    $chunks = array_chunk($activations, 9);

    if (isset($_GET['file'])) {
        $index = (int) $_GET['file'];
        $filename = "activation_adobe_part_{$index}.csv";

        $chunk = $chunks[$index];
        $file = fopen($filename, 'w');
        fwrite($file, "\xEF\xBB\xBF");

        $header = ["Type d’identité", "Nom d’utilisateur", "Domaine", "E-mail", "Prénom", "Nom", "Rôle de produit"];
        fwrite($file, implode(',', $header) . "\n");

        foreach ($chunk as $line) {
            // Convertissez votre objet $line en tableau si nécessaire
            $lineArray = (array) $line;

            $lineArray = [
                "Adobe ID",
                $lineArray['email'],
                substr(strrchr($lineArray['email'], "@"), 1),
                $lineArray['email'],
                $lineArray['firstname'],
                $lineArray['lastname'],
                "Utilisateur",
            ];

            fwrite($file, implode(',', $lineArray) . "\n");
        }

        fclose($file);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        readfile($filename);
        exit;
    }

    if (isset($_GET['activate'])) {
        $index = (int) $_GET['activate'];
        $chunk = $chunks[$index];

        foreach ($chunk as $line) {
            Capsule::table('tblhosting')
                ->where('id', $line->hosting_id)
                ->update(['domainstatus' => 'Active']);
        }

        header('Location: ' . $modulelink);
        exit;
    }

    $output = '<table class="table table-striped">';
    $output .= '<thead class="thead-dark">';
    $output .= '<tr><th>Nom</th><th>Action</th></tr>';
    $output .= '</thead>';
    $output .= '<tbody>';

    foreach ($chunks as $index => $chunk) {
        $filename = "activation_adobe_part_{$index}.csv";

        $output .= '<tr>';
        $output .= '<td>' . $filename . '</td>';

        $output .= '<td>';
        $output .= '<a class="btn btn-primary my-auto" href="' . $modulelink . '&file=' . urlencode($index) . '"><i class="fas fa-download"></i> Télécharger le CSV</a>';
        $output .= ' <a class="btn btn-success my-auto" href="' . $modulelink . '&activate=' . urlencode($index) . '"><i class="fas fa-check"></i> Activer les services</a>';
        $output .= '</td>';
        $output .= '</tr>';
    }

    $output .= '</tbody>';
    $output .= '</table>';

    echo $output;
}
