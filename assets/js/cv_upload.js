/**
 * CV Upload and AI Auto-fill Module
 * Handles CV file upload, AI parsing, and profile form auto-fill
 * Supports both full page load and Turbo Drive navigation
 */

function initCVUpload() {
    const dropZone = document.getElementById('cv-drop-zone');
    const fileInput = document.getElementById('cv-file-input');
    if (!dropZone || !fileInput) return;
    const uploadSection = document.getElementById('cv-upload-section');
    const formSection = document.getElementById('profile-form-section');

    const progressContainer = document.getElementById('cv-upload-progress');
    const progressBar = document.getElementById('cv-progress-bar');
    const progressText = document.getElementById('cv-progress-text');
    const aiProcessing = document.getElementById('cv-ai-processing');
    const successMessage = document.getElementById('cv-success');
    const errorMessage = document.getElementById('cv-error');
    const errorText = document.getElementById('cv-error-text');

    // Hide all status messages
    function hideAllMessages() {
        progressContainer.classList.add('hidden');
        aiProcessing.classList.add('hidden');
        successMessage.classList.add('hidden');
        errorMessage.classList.add('hidden');
    }

    // Show error message
    function showError(message) {
        hideAllMessages();
        errorText.textContent = message;
        errorMessage.classList.remove('hidden');
    }

    // Show success message
    function showSuccess() {
        hideAllMessages();
        successMessage.classList.remove('hidden');
    }

    // Update progress
    function updateProgress(percent) {
        progressBar.style.width = percent + '%';
        progressText.textContent = percent + '%';
    }

    // Show form section
    function showFormSection() {
        formSection.style.display = 'block';
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
        progressContainer.classList.remove('hidden');
        updateProgress(0);

        const formData = new FormData();
        formData.append('cv_file', file);

        try {
            // Simulate upload progress
            updateProgress(30);

            const url = new URL('/worker/profiles/parse-cv', window.location.origin);
            url.searchParams.append('collect', 'true');

            const response = await fetch(url, {
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
            console.log('AI Service Response:', data);

            updateProgress(100);
            progressContainer.classList.add('hidden');
            aiProcessing.classList.remove('hidden');

            // Simulate AI processing delay
            await new Promise(resolve => setTimeout(resolve, 1500));

            if (data.success) {
                fillFormWithData(data.data);
                showSuccess();
                showFormSection();
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

    // Fill form with extracted data
    function fillFormWithData(data) {
        console.log('Filling form with data:', data);
        // Title
        if (data.title) {
            const titleInput = document.querySelector('[name*="[title]"]');
            console.log('Filling title:', data.title, 'found input:', !!titleInput);
            if (titleInput) {
                titleInput.value = data.title;
                titleInput.classList.add('ai-filled');
            }
        }

        // Bio
        if (data.bio) {
            const bioInput = document.querySelector('[name*="[bio]"]');
            console.log('Filling bio:', data.bio.substring(0, 30) + '...', 'found input:', !!bioInput);
            if (bioInput) {
                bioInput.value = data.bio;
                bioInput.classList.add('ai-filled');
            }
        }

        // Experience years
        if (data.experience_years !== null && data.experience_years !== undefined) {
            const expInput = document.querySelector('[name*="[experience_years]"]');
            if (expInput) {
                expInput.value = data.experience_years;
                expInput.classList.add('ai-filled');
            }
        }

        // Hourly rate
        if (data.hourly_rate) {
            const rateInput = document.querySelector('[name*="[hourly_rate]"]');
            if (rateInput) {
                rateInput.value = data.hourly_rate;
                rateInput.classList.add('ai-filled');
            }
        }

        // Location
        if (data.location) {
            const locationInput = document.querySelector('[name*="[location]"]');
            if (locationInput) {
                locationInput.value = data.location;
                locationInput.classList.add('ai-filled');

                // Sync map with location
                syncMapWithLocation(data.location);
            }
        }

        // Phone Number
        if (data.phoneNumber) {
            const phoneInput = document.querySelector('[name*="[phoneNumber]"]');
            console.log('Filling phone:', data.phoneNumber, 'found input:', !!phoneInput);
            if (phoneInput) {
                phoneInput.value = data.phoneNumber;
                phoneInput.classList.add('ai-filled');
            }
        }

        // Email
        if (data.email) {
            const emailInput = document.querySelector('[name*="[email]"]');
            console.log('Filling email:', data.email, 'found input:', !!emailInput);
            if (emailInput) {
                emailInput.value = data.email;
                emailInput.classList.add('ai-filled');
            }
        }

        // Add visual indicator for AI-filled fields
        addAiFilledIndicators();
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
    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-[#0FAF7A]', 'bg-[#0FAF7A]/5');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-[#0FAF7A]', 'bg-[#0FAF7A]/5');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-[#0FAF7A]', 'bg-[#0FAF7A]/5');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });

    /**
     * Geocode location string and update Leaflet map/marker
     * @param {string} query - The location name or address
     */
    async function syncMapWithLocation(query) {
        if (!query || !window.leafletMap) return;

        console.log('Geocoding location:', query);

        try {
            // Priority search for better results
            const searchQuery = query.includes(',') ? query : `${query}`;
            const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}&limit=1`);
            const results = await response.json();

            if (results && results.length > 0) {
                const lat = parseFloat(results[0].lat);
                const lon = parseFloat(results[0].lon);
                console.log('Geocoding success:', lat, lon);

                // Update map view
                window.leafletMap.setView([lat, lon], 13);

                // Update or create marker
                if (window.leafletMarker) {
                    window.leafletMarker.setLatLng([lat, lon]);
                } else {
                    const L = window.L;
                    if (L) {
                        window.leafletMarker = L.marker([lat, lon]).addTo(window.leafletMap);
                    }
                }

                // Update hidden coordinate fields
                const latInput = document.querySelector('[name*="[latitude]"]');
                const lngInput = document.querySelector('[name*="[longitude]"]');

                if (latInput) {
                    latInput.value = lat.toFixed(7);
                    latInput.classList.add('ai-filled');
                }
                if (lngInput) {
                    lngInput.value = lon.toFixed(7);
                    lngInput.classList.add('ai-filled');
                }
            }
        } catch (error) {
            console.error('Geocoding error:', error);
        }
    }
}

document.addEventListener('DOMContentLoaded', initCVUpload);
document.addEventListener('turbo:load', initCVUpload);
