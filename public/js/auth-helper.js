/**
 * JWT Authentication Helper - localStorage based
 * 
 * This script:
 * - Stores JWT token in localStorage (key: 'token')
 * - Intercepts all fetch requests and adds Authorization header
 * - Intercepts all form submissions and adds JWT token
 * - Provides helper functions for API calls
 */

// Remove legacy key so only "token" is used
if (typeof localStorage !== "undefined" && localStorage.getItem("auth_token")) {
    localStorage.removeItem("auth_token");
}

/**
 * Get JWT token from localStorage
 */
function getToken() {
    return localStorage.getItem("token");
}

/**
 * Set JWT token in localStorage
 */
function setToken(token) {
    if (token) {
        localStorage.setItem("token", token);
    } else {
        localStorage.removeItem("token");
    }
}

/**
 * Remove JWT token from localStorage
 */
function clearToken() {
    localStorage.removeItem("token");
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return !!getToken();
}

/**
 * Enhanced fetch that automatically adds Authorization header
 */
function apiFetch(url, options) {
    options = options || {};
    options.headers = options.headers || {};
    
    const token = getToken();
    if (token) {
        options.headers["Authorization"] = "Bearer " + token;
    }
    
    return fetch(url, options);
}

/**
 * Intercept all native fetch calls and add Authorization header
 */
(function() {
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        options = options || {};
        options.headers = options.headers || {};
        
        const token = getToken();
        if (token && !options.headers["Authorization"] && !options.headers["authorization"]) {
            options.headers["Authorization"] = "Bearer " + token;
        }
        
        return originalFetch(url, options);
    };
})();

/**
 * Intercept all form submissions and add JWT token
 * Forms can include JWT via:
 * 1. Authorization header (for AJAX forms)
 * 2. Hidden input field (for regular form submissions)
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // Intercept form submissions
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            
            // Skip if form already has JWT token input
            if (form.querySelector('input[name="_jwt_token"]')) return;
            
            // Skip login/logout/register forms (they don't need JWT)
            const action = form.action || '';
            if (action.includes('/login') || 
                action.includes('/logout') || 
                action.includes('/register') ||
                action.includes('/api/login') ||
                action.includes('/api/logout')) {
                return;
            }
            
            const token = getToken();
            if (token) {
                // For AJAX forms, add Authorization header
                if (form.dataset.ajax === 'true' || form.hasAttribute('data-ajax')) {
                    // Form will be handled by AJAX, fetch interceptor will add header
                    return;
                }
                
                // For regular form submissions, add hidden input
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = '_jwt_token';
                hiddenInput.value = token;
                form.appendChild(hiddenInput);
            }
        });
    });
})();

/**
 * Intercept XMLHttpRequest and add Authorization header
 */
(function() {
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;
    
    XMLHttpRequest.prototype.open = function(method, url, ...args) {
        this._url = url;
        return originalOpen.apply(this, [method, url, ...args]);
    };
    
    XMLHttpRequest.prototype.send = function(...args) {
        const token = getToken();
        if (token && this._url && !this._url.includes('/login') && !this._url.includes('/logout')) {
            this.setRequestHeader('Authorization', 'Bearer ' + token);
        }
        return originalSend.apply(this, args);
    };
})();

/**
 * Fetch current token from API if not in localStorage (for users already logged in via cookie)
 * This handles cases where user logged in via face login or cookie-based auth
 */
(function() {
    // Only fetch if we don't have a token in localStorage
    if (!getToken()) {
        // Try to get token from /api/refresh endpoint (returns current token if authenticated)
        fetch('/api/refresh', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include', // Include cookies
        })
        .then(res => res.json().catch(() => ({})))
        .then(data => {
            if (data.token) {
                setToken(data.token);
                console.log('Synced JWT token to localStorage');
            }
        })
        .catch(() => {
            // User not authenticated or endpoint doesn't exist, ignore
        });
    }
})();

/**
 * Handle logout responses that signal token clearing
 */
(function() {
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        return originalFetch(url, options).then(response => {
            // Check if response indicates logout
            if (response.ok) {
                response.clone().json().then(data => {
                    if (data && data.clearToken === true) {
                        clearToken();
                    }
                }).catch(() => {
                    // Not JSON, ignore
                });
            }
            return response;
        });
    };
})();

// Make functions globally available
window.getToken = getToken;
window.setToken = setToken;
window.clearToken = clearToken;
window.isAuthenticated = isAuthenticated;
window.apiFetch = apiFetch;
