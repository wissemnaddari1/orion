# CV Upload JavaScript Fix - Testing Guide

## What Was Fixed

1. **Moved JavaScript file** from `assets/js/` to `public/js/cv_upload.js`
2. **Added console logging** for debugging
3. **Added null checks** to prevent errors if elements are missing
4. **Improved event handlers** with better error handling

## How to Test

### Step 1: Clear Browser Cache
Press `Ctrl + Shift + R` (or `Cmd + Shift + R` on Mac) to hard refresh the page

### Step 2: Open Browser Console
1. Press `F12` to open Developer Tools
2. Click on the "Console" tab
3. Navigate to: `http://127.0.0.1:8000/worker/profiles/new`

### Step 3: Check Console Messages
You should see these messages in the console:
```
CV Upload script loaded
Elements found: {dropZone: true, fileInput: true, uploadSection: true, formSection: true, skipButton: true}
Setting up event listeners...
Event listeners attached successfully
```

### Step 4: Test Skip Button
1. Click the "Skip and fill manually →" button
2. You should see in console: `Skip button clicked`
3. The CV upload section should hide
4. The profile form should appear below

### Step 5: Test Drop Zone
1. Refresh the page
2. Click anywhere on the dashed border area (drop zone)
3. You should see in console: `Drop zone clicked`
4. A file picker dialog should open

## If Buttons Still Don't Work

### Check 1: Verify JavaScript is Loading
In browser console, type:
```javascript
document.getElementById('skip-cv-upload')
```
If it returns `null`, the element doesn't exist. If it returns an element, the JavaScript can find it.

### Check 2: Check for JavaScript Errors
Look for red error messages in the console. Common issues:
- "Cannot read property 'addEventListener' of null" - element not found
- "cv_upload.js:1 Failed to load" - file not found

### Check 3: Verify File Path
In browser console, check if the script loaded:
```javascript
performance.getEntriesByType("resource").filter(r => r.name.includes('cv_upload'))
```

## Quick Fix Commands

If the file isn't loading, run:
```bash
# Copy the file again
copy assets\js\cv_upload.js public\js\cv_upload.js

# Check if file exists
dir public\js\cv_upload.js
```

## Manual Test Without AI Service

You can test the UI without the AI service running:
1. Click "Skip and fill manually" - form should appear
2. Click drop zone - file picker should open
3. Select a file - you'll get an error (expected if AI service is off), but form should still appear

## Expected Behavior

✅ **Skip button**: Hides upload section, shows form
✅ **Drop zone click**: Opens file picker
✅ **File selection**: Shows progress, attempts upload
✅ **Error handling**: Shows error message if upload fails, but still shows form
