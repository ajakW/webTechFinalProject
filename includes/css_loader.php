<?php
/**
 * CSS Loader Helper
 * Centralized CSS loading logic for different views
 */

// Base styles (always loaded)
echo '<link rel="stylesheet" href="css/base.css?v=' . time() . '">' . "\n";
echo '<link rel="stylesheet" href="css/layout.css?v=' . time() . '">' . "\n";
echo '<link rel="stylesheet" href="css/components.css?v=' . time() . '">' . "\n";

// External Fonts & Icons
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">' . "\n";
echo '<link rel="preconnect" href="https://fonts.gstatic.com">' . "\n";
echo '<link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Play&display=swap" rel="stylesheet">' . "\n";

// View-specific styles
$view = $view ?? $_GET['view'] ?? 'default';

switch ($view) {
    case 'dashboard':
        echo '<link rel="stylesheet" href="css/dashboard.css?v=' . time() . '">' . "\n";
        break;

    case 'admin':
    case 'edit_user':
        echo '<link rel="stylesheet" href="css/dashboard.css?v=' . time() . '">' . "\n";
        echo '<link rel="stylesheet" href="css/admin.css?v=' . time() . '">' . "\n";
        break;

    case 'read':
        echo '<link rel="stylesheet" href="css/reading.css?v=' . time() . '">' . "\n";
        break;

    default:
        echo '<link rel="stylesheet" href="css/landing.css?v=' . time() . '">' . "\n";
}
?>