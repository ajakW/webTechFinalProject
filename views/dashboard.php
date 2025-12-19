<div class="dashboard-layout">
    <!-- SIDEBAR -->
    <div class="dashboard-sidebar">
        <div class="sidebar-profile">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($userData['username'], 0, 2)); ?>
            </div>
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($userData['username']); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($userData['email']); ?></div>
            </div>
        </div>

        <div class="sidebar-search">
            <input type="text" id="sessionSearch" placeholder="Search reading list...">
        </div>

        <div class="sidebar-nav">
            <a href="#" class="nav-item" onclick="filterSessions('progress', this)">
                In Progress
            </a>
            <a href="#" class="nav-item" onclick="filterSessions('completed', this)">
                Completed
            </a>
        </div>

        <div class="sidebar-footer">
            <a href="index.php" class="nav-item home-item" title="Home / Upload">
                Home
            </a>
            <a href="logout.php" class="nav-item logout-item" title="Logout">
                Logout
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="dashboard-main">
        <div class="main-header">
            <h2 id="mainTitle"></h2>
            <div class="header-actions">
                <!-- Shows upload form only when needed or via modal -->
            </div>
        </div>



        <div class="sessions-grid" id="sessionsGrid" style="display: none;">
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <p>No reading sessions yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($sessions as $session):
                    $progress = $session['word_count'] > 0 ? ($session['current_word_index'] / $session['word_count']) * 100 : 0;
                    $progress = min(100, max(0, $progress));

                    // Use database status OR calculation
                    $isCompleted = (isset($session['status']) && $session['status'] === 'completed') || $progress >= 99.5;

                    $statusClass = $isCompleted ? 'status-completed' : 'status-progress';
                    ?>
                    <div class="session-card <?php echo $statusClass; ?>"
                        data-title="<?php echo strtolower(htmlspecialchars($session['material_name'])); ?>">
                        <div class="session-header">
                            <h3 class="session-title"><?php echo htmlspecialchars($session['material_name']); ?></h3>
                            <?php if (!empty($session['original_title']) && $session['original_title'] !== $session['material_name']): ?>
                                <span class="session-meta"><?php echo htmlspecialchars($session['original_title']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="session-progress">
                            <div class="progress-label"><?php echo number_format($progress, 0); ?>%</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <?php if (isset($session['average_wpm']) && $session['average_wpm'] > 0): ?>
                                <div style="font-size: 0.85em; color: #666; margin-top: 5px;">
                                    <span title="Average reading speed">⚡ <?php echo number_format($session['average_wpm'], 0); ?>
                                        WPM</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 10px;">
                            <a href="index.php?view=read&session_id=<?php echo $session['session_id']; ?>"
                                class="btn-resume <?php echo $isCompleted ? 'btn-completed-action' : ''; ?>" style="flex: 1;">
                                <?php echo $isCompleted ? 'Read Again' : 'Continue'; ?>
                            </a>
                            <button onclick="deleteSession(<?php echo $session['session_id']; ?>, this)" class="btn-delete"
                                title="Delete Session">
                                Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>