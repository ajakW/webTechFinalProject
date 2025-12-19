
/**
 * Helper function to wait for Hyphenopoly to be ready
 */
async function waitForHyphenopoly(maxWait = 5000) {
    const startTime = Date.now();
    while (Date.now() - startTime < maxWait) {
        if (typeof Hyphenopoly !== 'undefined' && Hyphenopoly.hyphenate) {
            try {
                const testResult = await Hyphenopoly.hyphenate('example', 'en-us');
                if (testResult && (testResult.includes('·') || testResult !== 'example')) {

                    return true;
                }
            } catch (e) {

            }
        }
        await new Promise(resolve => setTimeout(resolve, 200));
    }

    console.info('Hyphenopoly not available (timeout). Using standard Bionic mode.');
    return false;
}

// --- Shared Helper Functions ---

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Define the function used for splitting words (Hyphenopoly)
// This is used by both landing (demo) and reading view
async function splitWord(word) {
    if (typeof Hyphenopoly === 'undefined' || !Hyphenopoly.hyphenate) {

        return [word];
    }

    try {
        const hyphenated = await Hyphenopoly.hyphenate(word, 'en-us');
        if (!hyphenated || (hyphenated === word && !hyphenated.includes('·'))) {
            return [word];
        }

        let syllables = hyphenated.split('·').filter(s => s.length > 0);
        if (syllables.length === 0) {
            syllables = [word];
        }
        return syllables;
    } catch (error) {
        console.error("Hyphenopoly hyphenation failed:", error);
        return [word];
    }
}
