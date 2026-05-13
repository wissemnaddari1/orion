/**
 * CV Upload and AI Auto-fill Module
 * Handles CV file upload, AI parsing, and profile form auto-fill
 */

document.addEventListener('DOMContentLoaded', function () {
    console.log('CV Upload script loaded');

    const dropZone = document.getElementById('cv-drop-zone');
    const fileInput = document.getElementById('cv-file-input');
    const uploadSection = document.getElementById('cv-upload-section');
    const formSection = document.getElementById('profile-form-section');
    const skipButton = document.getElementById('skip-cv-upload');

    // Debug: Check if elements exist
    console.log('Elements found:', {
        dropZone: !!dropZone,
        fileInput: !!fileInput,
        uploadSection: !!uploadSection,
        formSection: !!formSection,
        skipButton: !!skipButton
    });

    // Exit if critical elements are missing
    if (!dropZone || !fileInput || !uploadSection || !formSection || !skipButton) {
        console.error('Critical elements missing!');
        return;
    }

    const progressContainer = document.getElementById('cv-upload-progress');
    const progressBar = document.getElementById('cv-progress-bar');
    const progressText = document.getElementById('cv-progress-text');
    const aiProcessing = document.getElementById('cv-ai-processing');
    const successMessage = document.getElementById('cv-success');
    const errorMessage = document.getElementById('cv-error');
    const errorText = document.getElementById('cv-error-text');

    // Hide all status messages
    function hideAllMessages() {
        if (progressContainer) progressContainer.classList.add('hidden');
        if (aiProcessing) aiProcessing.classList.add('hidden');
        if (successMessage) successMessage.classList.add('hidden');
        if (errorMessage) errorMessage.classList.add('hidden');
    }

    // Show error message
    function showError(message) {
        hideAllMessages();
        if (errorText) errorText.textContent = message;
        if (errorMessage) errorMessage.classList.remove('hidden');
    }

    // Show success message
    function showSuccess() {
        hideAllMessages();
        if (successMessage) successMessage.classList.remove('hidden');
    }

    // Update progress
    function updateProgress(percent) {
        if (progressBar) progressBar.style.width = percent + '%';
        if (progressText) progressText.textContent = percent + '%';
    }

    // Show form section
    function showFormSection() {
        formSection.style.display = 'block';
        if (window.leafletMap) {
            setTimeout(() => {
                try {
                    window.leafletMap.invalidateSize();
                } catch (e) {
                    console.warn('Leaflet invalidateSize failed:', e);
                }
            }, 120);
        }
        formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Handle file selection
    function handleFileSelect(file) {
        if (!file) return;

        // Validate file type
        const allowedTypes = ['application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg', 'image/png'];

        if (!allowedTypes.includes(file.type)) {
            showError('Invalid file type. Please upload PDF, DOC, DOCX, JPG, or PNG.');
            return;
        }

        // Validate file size (10MB)
        if (file.size > 10 * 1024 * 1024) {
            showError('File too large. Maximum size is 10MB.');
            return;
        }

        uploadCV(file);
    }

    // Upload CV and parse with AI
    async function uploadCV(file) {
        hideAllMessages();
        if (progressContainer) progressContainer.classList.remove('hidden');
        updateProgress(0);

        const formData = new FormData();
        formData.append('cv_file', file);

        try {
            // Simulate upload progress
            updateProgress(30);

            const response = await fetch('/worker/profiles/parse-cv', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            updateProgress(60);

            if (!response.ok) {
                throw new Error('Upload failed');
            }

            const data = await response.json();

            updateProgress(100);
            if (progressContainer) progressContainer.classList.add('hidden');
            if (aiProcessing) aiProcessing.classList.remove('hidden');

            // Simulate AI processing delay
            await new Promise(resolve => setTimeout(resolve, 1500));

            if (data.success) {
                fillFormWithData(data.data);
                showSuccess();
                showFormSection();

                // New: Sync map to extracted location
                if (data.data.location) {
                    syncMapToLocation(data.data.location);
                }
            } else {
                showError(data.error || 'Failed to parse CV. You can still fill the form manually.');
                showFormSection();
            }

        } catch (error) {
            console.error('CV upload error:', error);
            showError('Failed to upload CV. Please try again or fill the form manually.');
            showFormSection();
        }
    }

    // Helper function to find input by name ending or ID
    function findInput(fieldName) {
        // Try exact match first
        let input = document.querySelector(`[name="worker_profile[${fieldName}]"]`);
        if (input) return input;

        // Try suffix match (e.g. name ends with [title])
        input = document.querySelector(`[name$="[${fieldName}]"]`);
        if (input) return input;

        // Try ID match (standard Symfony ID structure: form_name_field_name)
        input = document.getElementById(`worker_profile_${fieldName}`);
        if (input) return input;

        // Try loose ID match
        const inputs = document.querySelectorAll('input, textarea, select');
        for (let i = 0; i < inputs.length; i++) {
            if (inputs[i].id && inputs[i].id.endsWith(`_${fieldName}`)) {
                return inputs[i];
            }
        }

        return null;
    }

    // Fill form with extracted data
    function fillFormWithData(data) {
        console.log('=== FILLING FORM WITH DATA (ROBUST MODE) ===');
        console.log('Data received:', data);

        const smartTitle = buildSmartProfessionalTitle(data);

        const fields = {
            'title': smartTitle,
            'bio': data.bio,
            'experience_years': data.experience_years,
            'hourly_rate': data.hourly_rate,
            'location': data.location,
            'phoneNumber': data.phone_number || data.phone || data.number,
            'email': data.email
        };

        for (const [key, value] of Object.entries(fields)) {
            if (value !== null && value !== undefined && value !== '') {
                const input = findInput(key);
                console.log(`Field '${key}': input found?`, !!input);

                if (input) {
                    // Handle special cases based on input type
                    if (input.tagName === 'SELECT') {
                        selectBestOption(input, value);
                    } else {
                        input.value = value;
                    }
                    input.classList.add('ai-filled');

                    // Dispatch change event to trigger any listeners (like floating labels)
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    input.dispatchEvent(new Event('input', { bubbles: true }));

                    // Add highlight effect
                    input.style.transition = 'background-color 0.5s';
                    input.style.backgroundColor = '#ecfdf5'; // light green
                    setTimeout(() => {
                        input.style.backgroundColor = '';
                    }, 2000);

                    console.log(`Field '${key}' set to:`, value);
                } else {
                    console.warn(`Could not find input for field '${key}'`);
                }
            } else {
                console.log(`Skipping field '${key}' because value is empty`);
            }
        }

        // Try to match Category based on Skills if not already set
        matchCategory(data.title, data.skills);

        console.log('=== FORM FILLING COMPLETE ===');

        // Add visual indicator for AI-filled fields
        addAiFilledIndicators();
    }

    function buildSmartProfessionalTitle(data) {
        const normalizeSpaces = (value) => (value || '').toString().replace(/\s+/g, ' ').trim();
        const titleCase = (value) => normalizeSpaces(value).toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
        const fixAcronyms = (value) => value
            .replace(/\bAi\b/g, 'AI')
            .replace(/\bUi\b/g, 'UI')
            .replace(/\bUx\b/g, 'UX')
            .replace(/\bQa\b/g, 'QA')
            .replace(/\bSeo\b/g, 'SEO')
            .replace(/\bIt\b/g, 'IT')
            .replace(/\bDevops\b/g, 'DevOps');

        const rawTitle = normalizeSpaces(data.title);
        const hasGoodTitle = rawTitle.length >= 4 && !/^(worker|freelancer|technician|employee)$/i.test(rawTitle);
        if (hasGoodTitle) {
            return fixAcronyms(titleCase(rawTitle));
        }

        const categorySelect = document.getElementById('worker_profile_workerCategory') || document.querySelector('select[name*="category"]');
        const selectedCategory = categorySelect && categorySelect.selectedIndex > 0
            ? categorySelect.options[categorySelect.selectedIndex].text
            : '';

        const skills = Array.isArray(data.skills) ? data.skills.filter(Boolean) : [];
        const bestSkill = skills.length > 0 ? skills[0] : '';
        const years = parseInt(data.experience_years, 10);

        let base = selectedCategory || bestSkill || 'Professional';
        base = fixAcronyms(titleCase(base));

        if (!Number.isNaN(years) && years > 0) {
            return `${base} (${years}+ yrs)`;
        }

        return base;
    }

    // Add visual indicators to AI-filled fields
    function addAiFilledIndicators() {
        const aiFilledFields = document.querySelectorAll('.ai-filled');
        aiFilledFields.forEach(field => {
            const container = field.closest('.group');
            if (container && !container.querySelector('.ai-indicator')) {
                const indicator = document.createElement('span');
                indicator.className = 'ai-indicator inline-flex items-center gap-1 text-xs text-[#0FAF7A] font-medium ml-2';
                indicator.innerHTML = `
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    AI filled
                `;
                const label = container.querySelector('label');
                if (label) {
                    label.appendChild(indicator);
                }
            }
        });
    }

    // Drag and drop events
    console.log('Setting up event listeners...');

    dropZone.addEventListener('click', function (e) {
        console.log('Drop zone clicked');
        fileInput.click();
    });

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZone.classList.add('border-[#0FAF7A]', 'bg-[#0FAF7A]/5');
    });

    dropZone.addEventListener('dragleave', function () {
        dropZone.classList.remove('border-[#0FAF7A]', 'bg-[#0FAF7A]/5');
    });

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('border-[#0FAF7A]', 'bg-[#0FAF7A]/5');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });

    fileInput.addEventListener('change', function (e) {
        console.log('File input changed');
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });

    // Skip button
    skipButton.addEventListener('click', function () {
        console.log('Skip button clicked');
        uploadSection.style.display = 'none';
        showFormSection();
    });

    console.log('Event listeners attached successfully');

    // Fuzzy match for dropdown options
    function selectBestOption(selectElement, searchText) {
        if (!searchText) return;
        console.log(`Trying to match category for: "${searchText}"`);

        const text = searchText.toLowerCase().trim();
        let bestMatch = null;
        let maxScore = 0;

        Array.from(selectElement.options).forEach(option => {
            if (option.value === "") return;

            const optText = option.text.toLowerCase().trim();
            let score = 0;

            // Log comparison for debugging
            // console.log(`Comparing "${text}" vs "${optText}"`);

            if (optText === text) {
                score = 100;
            } else if (text.includes(optText)) {
                // If title contains category (e.g. "Graphic Designer" contains "Design")
                score = 50 + optText.length;
            } else if (optText.includes(text)) {
                // If category contains title (unlikely but possible)
                score = 50 + text.length;
            }

            if (score > maxScore) {
                maxScore = score;
                bestMatch = option;
            }
        });

        if (bestMatch) {
            selectElement.value = bestMatch.value;
            console.log(`MATCH FOUND: Selected '${bestMatch.text}' (Score: ${maxScore})`);
            return true;
        } else {
            console.log('No match found for category.');
            return false;
        }
    }

    // Attempt to auto-select category
    function matchCategory(title, skills) {
        console.log('Starting Category Auto-selection...');

        // Try multiple selectors for category
        const categorySelect = document.getElementById('worker_profile_workerCategory') ||
            document.querySelector('select[name*="category"]');

        if (!categorySelect) {
            console.warn('Category select element not found!');
            return;
        }

        if (categorySelect.value === "") {
            let matched = false;

            // Try matching with Title first
            if (title) {
                matched = selectBestOption(categorySelect, title);
            }

            // If still empty and we have skills, try those
            if (!matched && skills && skills.length > 0) {
                console.log('Title match failed, trying skills:', skills);
                for (const skill of skills) {
                    matched = selectBestOption(categorySelect, skill);
                    if (matched) break; // Stop after first match
                }
            }

            if (categorySelect.value !== "") {
                categorySelect.classList.add('ai-filled');
                categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
                // Add highlight
                categorySelect.style.backgroundColor = '#ecfdf5';
                setTimeout(() => categorySelect.style.backgroundColor = '', 2000);
            }
        } else {
            console.log('Category is already set, skipping auto-selection.');
        }
    }

    // New: Sync map marker to extracted location
    async function syncMapToLocation(locationText, retries = 6) {
        if (!locationText) return;

        if (!window.leafletMap) {
            if (retries > 0) {
                setTimeout(() => syncMapToLocation(locationText, retries - 1), 200);
            }
            return;
        }

        console.log(`Geocoding location: "${locationText}"...`);

        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(locationText)}`);
            const data = await response.json();

            if (data && data.length > 0) {
                const result = data[0];
                const lat = parseFloat(result.lat);
                const lon = parseFloat(result.lon);

                console.log(`Geocoding success: ${lat}, ${lon}`);

                // Update map view
                window.leafletMap.setView([lat, lon], 13);

                // Update or create marker
                if (window.leafletMarker) {
                    window.leafletMarker.setLatLng([lat, lon]);
                } else {
                    window.leafletMarker = L.marker([lat, lon]).addTo(window.leafletMap);
                }

                // Update hidden coordinate fields
                const latInput = findInput('latitude');
                const lngInput = findInput('longitude');

                if (latInput) latInput.value = lat.toFixed(7);
                if (lngInput) lngInput.value = lon.toFixed(7);

                console.log('Map synchronized to location.');
            } else {
                console.warn('Geocoding found no results for:', locationText);
            }
        } catch (error) {
            console.error('Geocoding failed:', error);
        }
    }
});
