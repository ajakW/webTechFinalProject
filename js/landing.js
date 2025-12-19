// Landing Page JavaScript
// Extracted from index.php for better organization

// Scrollable Storytelling Functionality
let typingAnimation = null;

function showDemo() {
    const demoSection = document.getElementById('demo-section');
    if (demoSection) {
        demoSection.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Trigger the demo conversion after a short delay
        setTimeout(() => {
            const inputElement = document.getElementById("inputText");
            if (inputElement && typeof convertText === 'function') {
                convertText(inputElement.value, 0);
            }
        }, 500);
    }
}

function hideDemo() {
    const demoSection = document.getElementById('demo-section');
    if (demoSection) {
        demoSection.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Bionic typing animation
function startBionicTyping() {
    const typingElement = document.getElementById('typingText');
    if (!typingElement) return;

    const texts = [
        "Reading becomes effortless when your eyes know where to focus.",
        "Bionic Reading guides your vision through artificial fixation points.",
        "Experience faster comprehension with less mental fatigue.",
        "Transform any text into a more readable format instantly."
    ];

    let currentTextIndex = 0;
    let currentCharIndex = 0;
    let isDeleting = false;

    function typeText() {
        const currentText = texts[currentTextIndex];

        if (!isDeleting) {
            // Typing
            const char = currentText[currentCharIndex];
            const partialText = currentText.substring(0, currentCharIndex + 1);

            // Apply bionic formatting
            const words = partialText.split(' ');
            const bionicWords = words.map(word => {
                if (word.length <= 1) return word;
                const midPoint = Math.ceil(word.length / 2);
                return `<strong>${word.substring(0, midPoint)}</strong>${word.substring(midPoint)}`;
            });

            typingElement.innerHTML = bionicWords.join(' ');
            currentCharIndex++;

            if (currentCharIndex === currentText.length) {
                setTimeout(() => { isDeleting = true; }, 2000);
            }
        } else {
            // Deleting
            const partialText = currentText.substring(0, currentCharIndex);
            const words = partialText.split(' ');
            const bionicWords = words.map(word => {
                if (word.length <= 1) return word;
                const midPoint = Math.ceil(word.length / 2);
                return `<strong>${word.substring(0, midPoint)}</strong>${word.substring(midPoint)}`;
            });

            typingElement.innerHTML = bionicWords.join(' ');
            currentCharIndex--;

            if (currentCharIndex === 0) {
                isDeleting = false;
                currentTextIndex = (currentTextIndex + 1) % texts.length;
            }
        }

        const typingSpeed = isDeleting ? 50 : 100;
        typingAnimation = setTimeout(typeText, typingSpeed);
    }

    typeText();
}

// Scroll progress and animations
function updateScrollProgress() {
    const scrollTop = window.pageYOffset;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const scrollPercent = (scrollTop / docHeight) * 100;

    const progressBar = document.getElementById('progressBar');
    if (progressBar) {
        progressBar.style.height = scrollPercent + '%';
    }
}

// Intersection Observer for animations
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.3,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const window = entry.target;
                window.classList.add('animate-in');

                // Start typing animation for window 1
                if (window.id === 'window-1' && !typingAnimation) {
                    setTimeout(startBionicTyping, 500);
                }
            }
        });
    }, observerOptions);

    document.querySelectorAll('.story-window').forEach(window => {
        observer.observe(window);
    });
}

// Initialize storytelling features
document.addEventListener('DOMContentLoaded', function () {
    // Scroll progress
    window.addEventListener('scroll', updateScrollProgress);

    // Initialize scroll animations
    initScrollAnimations();

    // Close demo on overlay click
    const demoOverlay = document.getElementById('demo-section');
    if (demoOverlay) {
        demoOverlay.addEventListener('click', function (e) {
            if (e.target === demoOverlay) {
                hideDemo();
            }
        });
    }

    // Real-time Demo Conversion
    const demoInput = document.getElementById('inputText');
    if (demoInput) {
        demoInput.addEventListener('input', function() {
             if (typeof convertText === 'function') {
                 convertText(this.value, 0);
             }
        });
    }
});

// Enhanced Upload Functionality
function resetUpload() {
    const fileInput = document.getElementById('docfile');
    const fileCard = document.getElementById('fileCard');
    const convertBtn = document.getElementById('convertBtn');
    const materialName = document.getElementById('material_name');
    const uploadStatus = document.getElementById('upload-status');

    if (fileInput) fileInput.value = '';
    if (fileCard) fileCard.style.display = 'none';
    if (convertBtn) convertBtn.disabled = true;
    if (materialName) materialName.value = '';
    if (uploadStatus) uploadStatus.textContent = '';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function displayFileInfo() {
    const fileInput = document.getElementById("docfile");
    const fileCard = document.getElementById("fileCard");
    const convertBtn = document.getElementById("convertBtn");
    const fcName = document.getElementById("fcName");
    const fcSize = document.getElementById("fcSize");
    const materialNameInput = document.getElementById("material_name");

    if (!fileInput || !fileCard || !convertBtn || !fcName) return;

    if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        fcName.textContent = file.name;
        if (fcSize) fcSize.textContent = formatFileSize(file.size);

        // Auto-fill material name with filename if user hasn't typed anything
        if (materialNameInput && !materialNameInput.value.trim()) {
            materialNameInput.value = file.name.replace(/\.[^/.]+$/, ""); // Remove extension
        }

        fileCard.style.display = "block";
        convertBtn.disabled = false;
    } else {
        fileCard.style.display = "none";
        convertBtn.disabled = true;
    }
}

// Initialize drag and drop functionality
document.addEventListener('DOMContentLoaded', function () {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('docfile');

    if (dropzone && fileInput) {
        // Click to browse
        dropzone.addEventListener('click', () => fileInput.click());

        // Drag and drop events
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });

        dropzone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                const allowedTypes = ['.pdf', '.docx'];
                const fileExt = '.' + file.name.split('.').pop().toLowerCase();

                if (allowedTypes.includes(fileExt)) {
                    fileInput.files = files;
                    displayFileInfo();
                } else {
                    alert('Please upload a PDF or DOCX file only.');
                }
            }
        });

        // File input change event
        fileInput.addEventListener('change', displayFileInfo);
    }
});
