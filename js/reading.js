/**
 * Reading View JavaScript
 * Handles bionic text conversion, progress tracking, and session management
 */

let syllableFunction = splitWord; // From common.js

let totalWordCount = 0;
let currentWordIndex = 0;
// Track the index where the current timing session started (for accurate WPM)
let referenceWordIndex = 0;
let isSaving = false;
let currentSessionId = null;

// --- READING ANALYTICS VARIABLES ---
let sessionStartTime = null;
let totalReadingTime = 0; // Total time in seconds
let lastActivityTime = null;
let sessionTimerInterval = null;
let currentWPM = 0;

/**
 * Converts the text into Bionic Reading format using syllable segmentation.
 * @param {string} text The text to convert.
 * @param {number} startIndex The word index to start at (for resuming).
 */
async function convertText(text, startIndex = 0) {
    const outputElement = document.getElementById("output");
    if (!outputElement) return;

    if (!text.trim()) {
        outputElement.innerHTML = "";
        return;
    }

    // Split text by double newlines to get paragraphs
    const paragraphsRaw = text.split(/\n\n+/);
    let visibleWordIndex = 0;

    // Process each paragraph
    const paragraphPromises = paragraphsRaw.map(async (paraText) => {
        if (!paraText.trim()) return "";

        const words = paraText.split(/(\s+)/);

        // Process words in this paragraph
        const processedWordsPromises = words.map(async word => {
            if (!word.trim()) return { type: 'text', content: word };

            const cleanWord = word.replace(/[^a-zA-Z0-9]/g, "");
            if (cleanWord.length === 0) return { type: 'text', content: word };

            let syllables;
            try {
                syllables = await syllableFunction(cleanWord);
            } catch (e) {
                syllables = [cleanWord];
            }

            const syllableCount = syllables.length;
            let boldBoundaryLength;
            let finalPrefix = "";
            let finalSuffix = "";

            if (syllableCount === 1) {
                if (cleanWord.length === 1) boldBoundaryLength = 1;
                else boldBoundaryLength = Math.ceil(cleanWord.length / 2);
            } else if (syllableCount === 2) {
                boldBoundaryLength = syllables[0].length;
            } else {
                const syllablesToBold = Math.ceil(syllableCount * 0.5);
                const cleanPrefix = syllables.slice(0, syllablesToBold).join("");
                boldBoundaryLength = cleanPrefix.length;
            }

            let prefixAlphanumericCount = 0;
            for (let i = 0; i < word.length; i++) {
                const char = word[i];
                if (/[a-zA-Z0-9]/.test(char)) {
                    if (prefixAlphanumericCount < boldBoundaryLength) {
                        finalPrefix += char;
                        prefixAlphanumericCount++;
                    } else {
                        finalSuffix = word.slice(i);
                        break;
                    }
                } else {
                    if (prefixAlphanumericCount < boldBoundaryLength) {
                        finalPrefix += char;
                    } else {
                        finalSuffix = word.slice(i);
                        break;
                    }
                }
            }
            if (finalSuffix === "") finalSuffix = word.slice(finalPrefix.length);

            return {
                type: 'token',
                prefix: finalPrefix,
                suffix: finalSuffix
            };
        });

        const processedWords = await Promise.all(processedWordsPromises);

        // Render words for this paragraph SEQUENTIALLY to keep IDs correct
        const paraHtmlContent = processedWords.map(item => {
            if (item.type === 'text') return item.content;

            const convertedWord = `<strong>${item.prefix}</strong>${item.suffix}`;
            const isRead = visibleWordIndex < startIndex;
            const isCurrent = visibleWordIndex === startIndex;

            const tokenClass = `word-token ${isRead ? 'pre-read' : ''} ${isCurrent ? 'current-token' : ''}`;
            const wordHtml = `<span data-word-index="${visibleWordIndex}" class="${tokenClass}">${convertedWord}</span>`;

            visibleWordIndex++;
            return wordHtml;
        }).join("");

        // Determine if this entire paragraph is already read (historical)
        const isFullyRead = visibleWordIndex <= startIndex;

        // Wrap in Paragraph Container with appropriate class
        return `<p class="reading-paragraph ${isFullyRead ? 'read' : ''}" data-read="${isFullyRead}">${paraHtmlContent}</p>`;
    });

    const allParagraphsHtml = (await Promise.all(paragraphPromises)).join("");

    totalWordCount = visibleWordIndex; // Update global count
    outputElement.innerHTML = allParagraphsHtml;

    // After rendering, ensure the current index is visible
    if (startIndex > 0) {
        scrollToWordIndex(startIndex, 'auto');
    }
    // Set reference for WPM calculation
    referenceWordIndex = startIndex;
    updateProgressUI(startIndex);

    // Initialize Timer Logic
    if (typeof initParagraphTimers === 'function') {
        initParagraphTimers();
    }
}

// --- SESSION MANAGEMENT FUNCTIONS ---

function updateProgressUI(index) {
    currentWordIndex = index;
    const progressEl = document.getElementById('progress-text');

    if (totalWordCount === 0 || !progressEl) return;

    const completionPercentage = Math.min(100, (index / totalWordCount) * 100).toFixed(1);

    progressEl.textContent = `${completionPercentage}% Complete (${index} / ${totalWordCount} words)`;

    // Update token styles visually
    const words = document.querySelectorAll('.word-token');
    words.forEach((wordEl, i) => {
        wordEl.classList.remove('current-token', 'read-token');
        if (i < index) {
            wordEl.classList.add('read-token');
        } else if (i === index) {
            wordEl.classList.add('current-token');
        }
    });

    // Update WPM display
    updateWPMDisplay();
}

// Save lock timestamp
let lastSaveAttemptTime = 0;

async function saveReadingProgress(isPauseAction = false) {
    const now = Date.now();

    // BREAK STALE LOCKS
    if (isSaving && (now - lastSaveAttemptTime > 5000)) {
        console.warn('⚠️ Breaking stale save lock!');
        isSaving = false;
    }

    // Concurrent save handling
    if (isSaving) {
        if (isPauseAction) {
            console.log('⏳ Save in progress, retrying pause in 500ms...');
            const statusEl = document.getElementById('status-message');
            if (statusEl) statusEl.textContent = 'Finishing previous save...';
            setTimeout(() => saveReadingProgress(true), 500);
        }
        return;
    }

    if (!currentSessionId || totalWordCount === 0) return;

    isSaving = true;
    lastSaveAttemptTime = now;

    const statusEl = document.getElementById('status-message');
    if (statusEl) statusEl.textContent = 'Saving...';

    try {
        // 1. Determine the highest visible word index
        const words = document.querySelectorAll('.word-token');
        let lastVisibleIndex = 0;

        const scrollPosition = window.scrollY + window.innerHeight;
        const totalHeight = document.documentElement.scrollHeight;
        const isAtBottom = scrollPosition >= (totalHeight - 50);

        if (isAtBottom) {
            lastVisibleIndex = totalWordCount;
            // console.log('📜 Reached bottom - MARKING 100% COMPLETE');
        } else {
            const readingLine = window.innerHeight * 0.15;
            for (let i = 0; i < words.length; i++) {
                const word = words[i];
                const rect = word.getBoundingClientRect();
                if (rect.top < readingLine) {
                    lastVisibleIndex = parseInt(word.getAttribute('data-word-index'));
                } else {
                    if (rect.top > window.innerHeight) break;
                }
            }
        }

        lastVisibleIndex = Math.min(lastVisibleIndex, totalWordCount);

        // Check availability of required data
        if (lastVisibleIndex !== currentWordIndex || isPauseAction) {
            updateProgressUI(lastVisibleIndex);

            const formData = new FormData();
            formData.append('action', 'save_progress');
            formData.append('session_id', currentSessionId);
            formData.append('word_index', lastVisibleIndex);

            const readingTime = getSessionReadingTime();
            formData.append('reading_time', readingTime);

            let wpm = 0;
            const wordsRead = lastVisibleIndex - referenceWordIndex;
            if (readingTime > 0 && wordsRead > 0) {
                wpm = Math.round((wordsRead / readingTime) * 60);
            }
            formData.append('wpm', wpm);
            const isComplete = (lastVisibleIndex >= totalWordCount);
            formData.append('is_complete', isComplete);

            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData,
            });
            const data = await response.json();

            if (data.success) {
                if (statusEl) statusEl.textContent = isPauseAction ? 'Saved! Redirecting...' : 'Progress Saved';
                resetSessionTimer();
                referenceWordIndex = lastVisibleIndex;

                if (isPauseAction) {
                    stopSessionTimer();
                    setTimeout(() => window.location.href = 'index.php?view=dashboard', 200);
                }
            } else {
                throw new Error(data.message || 'Server returned error');
            }
        } else {
            if (statusEl && !isPauseAction) statusEl.textContent = '';
        }

    } catch (error) {
        console.error('❌ Save failed:', error);
        if (statusEl) statusEl.textContent = 'Save failed. Retrying...';
        if (isPauseAction) isSaving = false;
    } finally {
        isSaving = false;
    }
}

// Disable scroll restoration
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

function scrollToWordIndex(index, behavior = 'smooth') {
    const targetWord = document.querySelector(`[data-word-index="${index}"]`);
    if (targetWord) {
        setTimeout(() => {
            targetWord.scrollIntoView({ behavior: behavior, block: 'center' });
        }, 100);
    }
}

// --- READING ANALYTICS FUNCTIONS ---

function startSessionTimer() {
    if (sessionTimerInterval) clearInterval(sessionTimerInterval);
    sessionStartTime = Date.now();
    lastActivityTime = Date.now();
    sessionTimerInterval = setInterval(() => {
        const timeEl = document.getElementById('reading-time-display');
        if (!timeEl) return;
        const seconds = getSessionReadingTime();
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        timeEl.textContent = `${m}m ${s}s`;
        updateWPMDisplay();
    }, 1000);
}

function stopSessionTimer() {
    if (sessionTimerInterval) {
        clearInterval(sessionTimerInterval);
        sessionTimerInterval = null;
    }
}

function resetSessionTimer() {
    sessionStartTime = Date.now();
}

function getSessionReadingTime() {
    if (!sessionStartTime) return 0;
    const currentTime = Date.now();
    const elapsedSeconds = Math.floor((currentTime - sessionStartTime) / 1000);
    const inactiveTime = (currentTime - lastActivityTime) / 1000;
    if (inactiveTime > 30) {
        return Math.max(0, elapsedSeconds - Math.floor(inactiveTime));
    }
    return elapsedSeconds;
}

function updateWPMDisplay() {
    const wpmEl = document.getElementById('wpm-display');
    if (!wpmEl) return;
    const readingTime = getSessionReadingTime();
    const wordsRead = currentWordIndex - referenceWordIndex;
    if (readingTime > 0 && wordsRead > 0) {
        currentWPM = Math.round((wordsRead / readingTime) * 60);
        wpmEl.textContent = `${currentWPM} WPM`;
    } else {
        wpmEl.textContent = '-- WPM';
    }
}

function trackActivity() {
    lastActivityTime = Date.now();
}

// --- TIME-AWARE FADING LOGIC ---

const PARAGRAPH_READ_TIME = 40000; // 40 seconds
const readingLine = window.innerHeight * 0.15; // 15% from top
const paragraphTimers = new Map();
let scrollTimerFrame = null;

function initParagraphTimers() {
    checkParagraphs();
    window.addEventListener('scroll', () => {
        if (!scrollTimerFrame) {
            scrollTimerFrame = requestAnimationFrame(() => {
                checkParagraphs();
                scrollTimerFrame = null;
            });
        }
    });
}

function checkParagraphs() {
    const now = Date.now();
    const paragraphs = document.querySelectorAll('.reading-paragraph:not(.read)');

    paragraphs.forEach(p => {
        const rect = p.getBoundingClientRect();
        const scrolledPastLine = rect.top < readingLine;

        if (scrolledPastLine && rect.bottom > 0) {
            if (!paragraphTimers.has(p)) {
                paragraphTimers.set(p, now);
            }
            const startTime = paragraphTimers.get(p);
            const elapsed = now - startTime;
            if (elapsed >= PARAGRAPH_READ_TIME) {
                p.classList.add('read');
                p.setAttribute('data-read', 'true');
            }
        }
    });

    if (paragraphTimers.size > 0) {
        setTimeout(checkParagraphs, 1000);
    }
}

// --- PDF EXPORT FUNCTION ---
window.exportBionicPDF = function () {
    const element = document.getElementById('output');
    const title = document.querySelector('.reading-header h2')?.textContent.trim() || 'Bionic Reading';

    const opt = {
        margin: [15, 15, 15, 15],
        filename: title.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, scrollY: 0, letterRendering: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };

    if (typeof html2pdf !== 'undefined') {
        document.body.classList.add('pdf-exporting');
        element.classList.add('pdf-exporting');

        setTimeout(() => {
            html2pdf().set(opt).from(element).save().then(() => {
                document.body.classList.remove('pdf-exporting');
                element.classList.remove('pdf-exporting');
            }).catch(err => {
                console.error('PDF Export Error:', err);
                document.body.classList.remove('pdf-exporting');
                element.classList.remove('pdf-exporting');
            });
        }, 300);
    } else {
        window.print();
    }
};

// --- INITIALIZATION ---

document.addEventListener('DOMContentLoaded', async function () {
    await waitForHyphenopoly();
    const outputEl = document.getElementById('output');

    if (outputEl && outputEl.dataset.sessionId) {
        currentSessionId = outputEl.dataset.sessionId;
        const textContentEl = document.getElementById('hiddenTextContent');

        if (textContentEl) {
            const originalText = textContentEl.value;
            const startIndex = parseInt(outputEl.dataset.startIndex || 0);

            await convertText(originalText, startIndex);

            const pauseBtn = document.getElementById('pause-reading-btn');
            if (pauseBtn) {
                pauseBtn.addEventListener('click', () => saveReadingProgress(true));
            }

            let scrollTimeout;
            window.addEventListener('scroll', () => {
                trackActivity();
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(saveReadingProgress, 5000);
            });

            setInterval(() => {
                if (!isSaving && currentSessionId) {
                    saveReadingProgress();
                }
            }, 10000);

            window.addEventListener('mousemove', trackActivity);
            window.addEventListener('keypress', trackActivity);
            startSessionTimer();
        }
    }
});
