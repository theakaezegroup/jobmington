<?php
/**
 * JOBMINGTON - World-Class CV Editor
 * Redirects to the complete CV editor
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/session.php';

Session::start();
Session::requireLogin();

$cvId = (int)($_GET['id'] ?? 0);

// Redirect to the complete editor
if ($cvId > 0) {
    header('Location: ' . SITE_URL . '/cv-builder/editor-complete.php?id=' . $cvId);
} else {
    header('Location: ' . SITE_URL . '/cv-builder/');
}
exit;

// Legacy code below - kept for reference
$pdo = db();
$userId = Session::userId();

// --- FETCH CV DATA ---
if ($cvId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM cv_profiles WHERE cv_id = ? AND user_id = ?");
    $stmt->execute([$cvId, $userId]);
    $cv = $stmt->fetch();
    
    if (!$cv) {
        http_response_code(404);
        require_once __DIR__ . '/../errors/404.php';
        exit;
    }
    
    // Fetch related data
    $stmt = $pdo->prepare("SELECT * FROM cv_experience WHERE cv_id = ? ORDER BY start_date DESC");
    $stmt->execute([$cvId]);
    $experiences = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM cv_education WHERE cv_id = ? ORDER BY start_date DESC");
    $stmt->execute([$cvId]);
    $education = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM cv_skills WHERE cv_id = ?");
    $stmt->execute([$cvId]);
    $skills = $stmt->fetchAll();
} else {
    http_response_code(404);
    require_once __DIR__ . '/../errors/404.php';
    exit;
}

$pageTitle = 'CV Editor | ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    body { background: #020617; }
    
    .editor-container {
        max-width: 1400px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 20px;
        padding: 20px;
    }
    
    @media (max-width: 1024px) {
        .editor-container { grid-template-columns: 1fr; }
        .preview-panel { display: none; }
    }
    
    .editor-panel {
        background: rgba(15, 23, 42, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 1.5rem;
        padding: 30px;
        backdrop-filter: blur(12px);
    }
    
    .editor-section {
        margin-bottom: 40px;
        padding-bottom: 40px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    .editor-section:last-child {
        border-bottom: none;
    }
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #fbbf24;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #cbd5e1;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .form-input, .form-textarea {
        width: 100%;
        padding: 12px 16px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.75rem;
        color: #f1f5f9;
        font-size: 1rem;
        transition: all 0.3s;
    }
    
    .form-input:focus, .form-textarea:focus {
        outline: none;
        border-color: #fbbf24;
        box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 100px;
        font-family: 'Inter', sans-serif;
    }
    
    .two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    @media (max-width: 640px) {
        .two-col {
            grid-template-columns: 1fr;
        }
    }
    
    .btn-add {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: rgba(251, 191, 36, 0.1);
        border: 2px solid #fbbf24;
        color: #fbbf24;
        border-radius: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 10px;
    }
    
    .btn-add:hover {
        background: #fbbf24;
        color: #020617;
    }
    
    .btn-save {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 30px;
        background: #fbbf24;
        color: #020617;
        border: none;
        border-radius: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.875rem;
    }
    
    .btn-save:hover {
        background: #f59e0b;
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(251, 191, 36, 0.3);
    }
    
    .btn-download {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 30px;
        background: transparent;
        color: #fbbf24;
        border: 2px solid #fbbf24;
        border-radius: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.875rem;
        text-decoration: none;
    }
    
    .btn-download:hover {
        background: rgba(251, 191, 36, 0.1);
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(251, 191, 36, 0.2);
    }
    
    .btn-group {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }

    .preview-panel {
        background: rgba(15, 23, 42, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 1.5rem;
        padding: 20px;
        backdrop-filter: blur(12px);
        position: sticky;
        top: 100px;
        height: fit-content;
    }
    
    .preview-label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 10px;
    }
    
    .ats-score {
        font-size: 2rem;
        font-weight: 900;
        color: #fbbf24;
        margin-bottom: 5px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 8px 12px;
        background: rgba(34, 197, 94, 0.2);
        border: 1px solid #22c55e;
        color: #86efac;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
</style>

<div class="pt-24 pb-12" style="max-width: 1400px; margin: 0 auto; padding-left: 20px; padding-right: 20px;">
    
    <!-- BACK BUTTON -->
    <div class="mb-4">
        <a href="<?= SITE_URL ?>/cv-builder/" class="text-slate-400 hover:text-white text-sm font-bold transition inline-block">
            <i class="fas fa-arrow-left mr-2"></i> Back to CV Builder
        </a>
    </div>
    
    <div class="editor-container" style="padding: 0;">
    
    <!-- MAIN EDITOR -->
    <div class="editor-panel">
        
        <form id="cvForm">
            <input type="hidden" name="cv_id" value="<?= $cvId ?>">
            
            <!-- PERSONAL SECTION -->
            <div class="editor-section">
                <div class="section-title">
                    <i class="fas fa-user text-white"></i> Personal Information
                </div>
                
                <div class="two-col">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-input" name="personal[full_name]" value="<?= e($cv['full_name'] ?? '') ?>" placeholder="Your Name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Headline</label>
                        <input type="text" class="form-input" name="personal[headline]" value="<?= e($cv['headline'] ?? '') ?>" placeholder="Senior Software Engineer">
                    </div>
                </div>
                
                <div class="two-col">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" name="personal[email]" value="<?= e($cv['email'] ?? '') ?>" placeholder="you@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-input" name="personal[phone]" value="<?= e($cv['phone'] ?? '') ?>" placeholder="+234 (0) XXX XXXX XXX">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">City / Location</label>
                    <input type="text" class="form-input" name="personal[city]" value="<?= e($cv['city'] ?? '') ?>" placeholder="Lagos, Nigeria">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Professional Summary</label>
                    <textarea class="form-textarea" name="personal[summary]" placeholder="Brief overview of your professional background..."><?= e($cv['summary'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- EXPERIENCE SECTION -->
            <div class="editor-section">
                <div class="section-title">
                    <i class="fas fa-briefcase text-white"></i> Work Experience
                </div>
                
                <div id="experienceList">
                    <?php foreach ($experiences as $index => $exp): ?>
                    <div class="exp-item" data-index="<?= $index ?>" style="padding: 20px; border: 1px solid rgba(255,255,255,0.05); border-radius: 0.75rem; margin-bottom: 15px; position: relative;">
                        <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 10px; right: 10px; background: rgba(239, 68, 68, 0.2); border: none; color: #ef4444; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="two-col">
                            <div class="form-group">
                                <label class="form-label">Job Title</label>
                                <input type="text" class="form-input exp-job-title" value="<?= e($exp['job_title']) ?>" placeholder="Job Title">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Company</label>
                                <input type="text" class="form-input exp-company" value="<?= e($exp['company']) ?>" placeholder="Company">
                            </div>
                        </div>
                        <div class="two-col" style="margin-top: 15px;">
                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" class="form-input exp-city" value="<?= e($exp['city'] ?? '') ?>" placeholder="City">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" class="exp-is-current" <?= !empty($exp['is_current']) ? 'checked' : '' ?>> Currently working here
                                </label>
                            </div>
                        </div>
                        <div class="two-col" style="margin-top: 15px;">
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="month" class="form-input exp-start-date" value="<?= $exp['start_date'] ? date('Y-m', strtotime($exp['start_date'])) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="month" class="form-input exp-end-date" value="<?= $exp['end_date'] ? date('Y-m', strtotime($exp['end_date'])) : '' ?>">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 15px;">
                            <label class="form-label">Description</label>
                            <textarea class="form-textarea exp-description" placeholder="Describe your responsibilities..."><?= e($exp['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" class="btn-add" onclick="addExperience()">
                    <i class="fas fa-plus"></i> Add Experience
                </button>
            </div>
            
            <!-- EDUCATION SECTION -->
            <div class="editor-section">
                <div class="section-title">
                    <i class="fas fa-graduation-cap text-white"></i> Education
                </div>
                
                <div id="educationList">
                    <?php foreach ($education as $index => $edu): ?>
                    <div class="edu-item" data-index="<?= $index ?>" style="padding: 20px; border: 1px solid rgba(255,255,255,0.05); border-radius: 0.75rem; margin-bottom: 15px; position: relative;">
                        <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 10px; right: 10px; background: rgba(239, 68, 68, 0.2); border: none; color: #ef4444; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="two-col">
                            <div class="form-group">
                                <label class="form-label">Degree</label>
                                <input type="text" class="form-input edu-degree" value="<?= e($edu['degree'] ?? '') ?>" placeholder="Bachelor of Science">
                            </div>
                            <div class="form-group">
                                <label class="form-label">School/University</label>
                                <input type="text" class="form-input edu-school" value="<?= e($edu['institution'] ?? '') ?>" placeholder="University Name">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 15px;">
                            <label class="form-label">Field of Study</label>
                            <input type="text" class="form-input edu-field" value="<?= e($edu['field_of_study'] ?? '') ?>" placeholder="Computer Science">
                        </div>
                        <div class="two-col" style="margin-top: 15px;">
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="month" class="form-input edu-start-date" value="<?= !empty($edu['start_date']) ? date('Y-m', strtotime($edu['start_date'])) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="month" class="form-input edu-end-date" value="<?= !empty($edu['end_date']) ? date('Y-m', strtotime($edu['end_date'])) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" class="btn-add" onclick="addEducation()">
                    <i class="fas fa-plus"></i> Add Education
                </button>
            </div>
            
            <!-- SKILLS SECTION -->
            <div class="editor-section">
                <div class="section-title">
                    <i class="fas fa-tools text-white"></i> Skills
                </div>
                
                <div id="skillsList" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                    <?php foreach ($skills as $skill): ?>
                    <div class="skill-tag" style="background: rgba(251, 191, 36, 0.1); border: 1px solid #fbbf24; padding: 8px 15px; border-radius: 2rem; display: flex; align-items: center; gap: 8px;">
                        <span class="skill-name"><?= e($skill['skill_name']) ?></span>
                        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #fbbf24; cursor: pointer; padding: 0;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="newSkillInput" class="form-input" placeholder="Type a skill and press Enter" style="flex: 1;">
                    <button type="button" class="btn-add" onclick="addSkill()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </div>
            
            <!-- SAVE & DOWNLOAD BUTTONS -->
            <div class="btn-group">
                <button type="submit" class="btn-save">
                    <i class="fas fa-floppy-disk"></i> Save Changes
                </button>
                <a href="<?= SITE_URL ?>/cv-builder/export.php?id=<?= $cvId ?>" target="_blank" class="btn-download">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>
        </form>
    </div>
    
    <!-- PREVIEW PANEL -->
    <div class="preview-panel">
        <div class="preview-label">ATS Compatibility Score</div>
        <div class="ats-score">85</div>
        <div class="status-badge">
            <i class="fas fa-check-circle"></i> Optimized
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
            <div class="preview-label">Quick Actions</div>
            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                <a href="<?= SITE_URL ?>/cv-builder/export.php?id=<?= $cvId ?>" target="_blank" class="btn-download" style="justify-content: center; padding: 12px;">
                    <i class="fas fa-eye"></i> Preview CV
                </a>
                <a href="<?= SITE_URL ?>/cv-builder/export.php?id=<?= $cvId ?>&print=1" target="_blank" class="btn-download" style="justify-content: center; padding: 12px;">
                    <i class="fas fa-print"></i> Print / Save PDF
                </a>
            </div>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: rgba(251, 191, 36, 0.1); border-radius: 0.75rem; border: 1px solid rgba(251, 191, 36, 0.3);">
            <p style="font-size: 0.75rem; color: #cbd5e1; margin: 0;">
                <i class="fas fa-lightbulb text-amber-400"></i> 
                <strong>Tip:</strong> Save your changes first, then click "Print / Save PDF" to download your CV.
            </p>
        </div>
    </div>
    
    </div><!-- end editor-container -->
</div><!-- end outer wrapper -->

<script>
    // Skill input on Enter
    document.getElementById('newSkillInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addSkill();
        }
    });

    document.getElementById('cvForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        
        // Collect experience data
        const experienceData = [];
        document.querySelectorAll('.exp-item').forEach(item => {
            experienceData.push({
                job_title: item.querySelector('.exp-job-title')?.value || '',
                company: item.querySelector('.exp-company')?.value || '',
                city: item.querySelector('.exp-city')?.value || '',
                start_date: item.querySelector('.exp-start-date')?.value || '',
                end_date: item.querySelector('.exp-end-date')?.value || '',
                is_current: item.querySelector('.exp-is-current')?.checked || false,
                description: item.querySelector('.exp-description')?.value || ''
            });
        });
        
        // Collect education data
        const educationData = [];
        document.querySelectorAll('.edu-item').forEach(item => {
            educationData.push({
                degree: item.querySelector('.edu-degree')?.value || '',
                school: item.querySelector('.edu-school')?.value || '',
                field_of_study: item.querySelector('.edu-field')?.value || '',
                city: '',
                start_date: item.querySelector('.edu-start-date')?.value || '',
                end_date: item.querySelector('.edu-end-date')?.value || '',
                description: ''
            });
        });
        
        // Collect skills data
        const skillsData = [];
        document.querySelectorAll('.skill-tag .skill-name').forEach(tag => {
            skillsData.push({
                name: tag.textContent.trim(),
                level: 50
            });
        });
        
        const cvData = {
            cv_id: formData.get('cv_id'),
            personal: {
                full_name: formData.get('personal[full_name]'),
                email: formData.get('personal[email]'),
                phone: formData.get('personal[phone]'),
                city: formData.get('personal[city]'),
                headline: formData.get('personal[headline]'),
                summary: formData.get('personal[summary]')
            },
            experience: experienceData,
            education: educationData,
            skills: skillsData
        };
        
        try {
            const response = await fetch('<?= SITE_URL ?>/cv-builder/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(cvData)
            });
            
            const result = await response.json();
            if (result.success) {
                JM.toast('CV saved successfully!', 'success');
            } else {
                JM.toast(result.message, 'error', 'Save Failed');
            }
        } catch (error) {
            JM.toast(error.message, 'error', 'Network Error');
        }
    });
    
    function addSkill() {
        const input = document.getElementById('newSkillInput');
        const skillName = input.value.trim();
        if (!skillName) return;
        
        const container = document.getElementById('skillsList');
        const tag = document.createElement('div');
        tag.className = 'skill-tag';
        tag.style = 'background: rgba(251, 191, 36, 0.1); border: 1px solid #fbbf24; padding: 8px 15px; border-radius: 2rem; display: flex; align-items: center; gap: 8px;';
        tag.innerHTML = `
            <span class="skill-name">${skillName}</span>
            <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #fbbf24; cursor: pointer; padding: 0;">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(tag);
        input.value = '';
    }
    
    function addExperience() {
        const container = document.getElementById('experienceList');
        const item = document.createElement('div');
        item.className = 'exp-item';
        item.style = 'padding: 20px; border: 1px solid rgba(255,255,255,0.05); border-radius: 0.75rem; margin-bottom: 15px; position: relative;';
        item.innerHTML = `
            <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 10px; right: 10px; background: rgba(239, 68, 68, 0.2); border: none; color: #ef4444; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
            <div class="two-col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Job Title</label>
                    <input type="text" class="form-input exp-job-title" placeholder="Job Title">
                </div>
                <div class="form-group">
                    <label class="form-label">Company</label>
                    <input type="text" class="form-input exp-company" placeholder="Company">
                </div>
            </div>
            <div class="two-col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" class="form-input exp-city" placeholder="City">
                </div>
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 8px; margin-top: 25px;">
                        <input type="checkbox" class="exp-is-current"> Currently working here
                    </label>
                </div>
            </div>
            <div class="two-col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="month" class="form-input exp-start-date">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="month" class="form-input exp-end-date">
                </div>
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label class="form-label">Description</label>
                <textarea class="form-textarea exp-description" placeholder="Describe your responsibilities and achievements..." style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.75rem; color: #f1f5f9; min-height: 80px;"></textarea>
            </div>
        `;
        container.appendChild(item);
    }
    
    function addEducation() {
        const container = document.getElementById('educationList');
        const item = document.createElement('div');
        item.className = 'edu-item';
        item.style = 'padding: 20px; border: 1px solid rgba(255,255,255,0.05); border-radius: 0.75rem; margin-bottom: 15px; position: relative;';
        item.innerHTML = `
            <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 10px; right: 10px; background: rgba(239, 68, 68, 0.2); border: none; color: #ef4444; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
            <div class="two-col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Degree</label>
                    <input type="text" class="form-input edu-degree" placeholder="Bachelor of Science">
                </div>
                <div class="form-group">
                    <label class="form-label">School/University</label>
                    <input type="text" class="form-input edu-school" placeholder="University Name">
                </div>
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label class="form-label">Field of Study</label>
                <input type="text" class="form-input edu-field" placeholder="Computer Science">
            </div>
            <div class="two-col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="month" class="form-input edu-start-date">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="month" class="form-input edu-end-date">
                </div>
            </div>
        `;
        container.appendChild(item);
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
