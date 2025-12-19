/**
 * Dashboard JavaScript
 * Handles search, filters, session management, and file uploads
 */

// --- Dashboard Functionality ---

/**
 * Filters dashboard sessions by category.
 * @param {string} filter 'progress', or 'completed.'
 * @param {HTMLElement} element The clicked nav link
 */
window.filterSessions = function (filter, element) {
    // Show Grid
    const grid = document.getElementById('sessionsGrid');
    if (grid) grid.style.display = 'grid';

    // Update specific dashboard title
    const titles = {
        'all': 'All Material',
        'progress': 'In Progress',
        'completed': 'Completed'
    };
    const titleEl = document.getElementById('mainTitle');
    if (titleEl && titles[filter]) titleEl.textContent = titles[filter];

    // Update Active Link
    if (element) {
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');
    }

    // Filter Cards
    const cards = document.querySelectorAll('.session-card');
    cards.forEach(card => {
        if (filter === 'all') {
            card.style.display = 'flex';
        } else if (filter === 'progress') {
            card.style.display = card.classList.contains('status-progress') ? 'flex' : 'none';
        } else if (filter === 'completed') {
            card.style.display = card.classList.contains('status-completed') ? 'flex' : 'none';
        }
    });
};

// --- DELETE SESSION FUNCTION ---
window.deleteSession = async function (sessionId, btnElement) {
    if (!confirm('Are you sure you want to delete this reading session? This action cannot be undone.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'delete_session');
        formData.append('session_id', sessionId);

        const response = await fetch('ajax_handler.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            // Remove the card from the DOM without reloading
            if (btnElement) {
                const card = btnElement.closest('.session-card');
                if (card) {
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';

                    setTimeout(() => {
                        card.remove();
                        // Check if grid is empty
                        const grid = document.getElementById('sessionsGrid');
                        const remainingCards = grid.querySelectorAll('.session-card');
                        if (remainingCards.length === 0) {
                            grid.innerHTML = '<div class="empty-state"><p>No reading sessions yet.</p></div>';
                        }
                    }, 300);
                    return;
                }
            }
            // Fallback if element wasn't passed or found
            window.location.reload();
        } else {
            alert('Error deleting session: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting session:', error);
        alert('Network error occurred while deleting session.');
    }
};

// --- UPLOAD HANDLING ---

// Display File Info (Extracted from old landing.js / script.js mix)
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

        if (materialNameInput && !materialNameInput.value.trim()) {
            materialNameInput.value = file.name.replace(/\.[^/.]+$/, "");
        }

        fileCard.style.display = "block";
        convertBtn.disabled = false;
    } else {
        fileCard.style.display = "none";
        convertBtn.disabled = true;
    }
}

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

// Window global for the reset button onclick
window.resetUpload = resetUpload;

// --- INITIALIZATION ---

document.addEventListener('DOMContentLoaded', function () {
    // Search Handler
    const searchInput = document.getElementById('sessionSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.session-card');

            cards.forEach(card => {
                const title = card.getAttribute('data-title');
                if (title && title.includes(term)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // Upload Form Handlers
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        // Drag & Drop
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('docfile');

        if (dropzone && fileInput) {
            dropzone.addEventListener('click', () => fileInput.click());
            dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
            dropzone.addEventListener('dragleave', (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); });
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    displayFileInfo();
                }
            });
            fileInput.addEventListener('change', displayFileInfo);
        }

        // AJAX Submission
        uploadForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const form = e.target;
            const statusEl = document.getElementById('upload-status');
            const uploadButton = document.getElementById('convertBtn');

            statusEl.textContent = 'Uploading and processing...';
            statusEl.style.color = '#5B8FA3';
            uploadButton.disabled = true;

            const formData = new FormData(form);

            try {
                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();

                if (data.success) {
                    statusEl.textContent = data.message + ' Redirecting...';
                    statusEl.style.color = '#7A9B7A';
                    window.location.href = data.redirect;
                } else {
                    statusEl.textContent = 'Error: ' + data.message;
                    statusEl.style.color = '#8B4A4A';
                }
            } catch (error) {
                statusEl.textContent = 'A network error occurred.';
                statusEl.style.color = '#8B4A4A';
                console.error('Upload error:', error);
            } finally {
                uploadButton.disabled = false;
            }
        });
    }
});
