<?php
/**
 * JavaScript Loader Helper
 * Centralized JavaScript loading logic for different views
 */

$view = $_GET['view'] ?? 'default';

// Hyphenopoly configuration and library (for reading and demo)
if ($view === 'read' || $view === 'default') {
    echo '<script>' . "\n";
    echo '    var Hyphenopoly = {' . "\n";
    echo '        require: ["en-us"],' . "\n";
    echo '        hyphen: "·",' . "\n";
    echo '        paths: {' . "\n";
    echo '            patterndir: "https://unpkg.com/hyphenopoly@6.2.0/patterns/",' . "\n";
    echo '            maindir: "https://unpkg.com/hyphenopoly@6.2.0/dist/"' . "\n";
    echo '        },' . "\n";
    echo '        setup: {' . "\n";
    echo '            selectors: {}' . "\n";
    echo '        }' . "\n";
    echo '    };' . "\n";
    echo '</script>' . "\n";
    echo '<script src="https://unpkg.com/hyphenopoly@6.2.0/dist/Hyphenopoly.js"></script>' . "\n";
}

// Common utilities (always loaded)
echo '<script src="js/common.js?v=' . time() . '"></script>' . "\n";

// View-specific scripts
switch ($view) {
    case 'read':
        // PDF library + Reading Logic
        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>' . "\n";
        echo '<script src="js/reading.js?v=' . time() . '"></script>' . "\n";
        break;

    case 'dashboard':
        // Dashboard management + Uploads
        echo '<script src="js/dashboard.js?v=' . time() . '"></script>' . "\n";
        break;

    default: // Landing / Login / Register
        // Landing animations + Public Demo (if any)
        echo '<script src="js/landing.js?v=' . time() . '"></script>' . "\n";
        // Landing Page uses convertText from reading.js
        echo '<script src="js/reading.js?v=' . time() . '"></script>' . "\n";
        echo '<script src="js/dashboard.js?v=' . time() . '"></script>' . "\n";
        break;
}
?>