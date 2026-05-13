import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["menu", "button", "label", "valueInput"];
    static values = {
        open: Boolean,
        selected: String
    }

    connect() {
        this.openValue = false;
        this.updateLabel();
        
        // Handle outside clicks
        this.clickOutsideHandler = this.clickOutside.bind(this);
        document.addEventListener('click', this.clickOutsideHandler);
        
        // Handle Escape key
        this.keydownHandler = this.keydown.bind(this);
        document.addEventListener('keydown', this.keydownHandler);
    }

    disconnect() {
        document.removeEventListener('click', this.clickOutsideHandler);
        document.removeEventListener('keydown', this.keydownHandler);
    }

    toggle() {
        this.openValue = !this.openValue;
    }

    openValueChanged() {
        if (this.openValue) {
            this.menuTarget.classList.remove('hidden');
            // Small delay to allow the removal of 'hidden' before transitioning
            requestAnimationFrame(() => {
                this.menuTarget.classList.remove('opacity-0', '-translate-y-2', 'pointer-events-none');
            });
            this.buttonTarget.querySelector('.chevron').classList.add('rotate-180');
        } else {
            this.menuTarget.classList.add('opacity-0', '-translate-y-2', 'pointer-events-none');
            this.buttonTarget.querySelector('.chevron').classList.remove('rotate-180');
            // Wait for transition to finish before hiding
            setTimeout(() => {
                if (!this.openValue) this.menuTarget.classList.add('hidden');
            }, 150);
        }
    }

    select(event) {
        const option = event.currentTarget;
        const value = option.dataset.value;
        const text = option.querySelector('.option-text').textContent;
        
        this.selectedValue = value;
        this.valueInputTarget.value = value;
        this.updateLabel(text);
        
        // Dispatch event for the list filtering logic
        this.valueInputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        
        this.openValue = false;
        this.updateActiveState(option);
    }

    updateLabel(text) {
        if (text) {
            this.labelTarget.textContent = `Filter: ${text}`;
        }
    }

    updateActiveState(selectedOption) {
        this.menuTarget.querySelectorAll('[data-action*="select"]').forEach(opt => {
            const check = opt.querySelector('.check-icon');
            if (opt === selectedOption) {
                opt.classList.add('bg-emerald-50', 'text-emerald-600', 'dark:bg-emerald-500/10');
                if (check) check.classList.remove('hidden');
            } else {
                opt.classList.remove('bg-emerald-50', 'text-emerald-600', 'dark:bg-emerald-500/10');
                if (check) check.classList.add('hidden');
            }
        });
    }

    clickOutside(event) {
        if (!this.element.contains(event.target) && this.openValue) {
            this.openValue = false;
        }
    }

    keydown(event) {
        if (event.key === "Escape" && this.openValue) {
            this.openValue = false;
        }
    }
}
