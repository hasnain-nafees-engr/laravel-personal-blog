// why: Livewire 3 bundles Alpine.js and starts it itself. Importing Alpine
// here as well (which Breeze's stub does) loads it twice and breaks every
// x-data component with "Alpine has already been initialised".
//
// So this file holds only what must run BEFORE Alpine boots.

// Apply the saved colour theme as early as possible to avoid a white flash
// on load for dark-mode users. The <html> class is what Tailwind's `dark:`
// variant keys off.
(() => {
    const stored = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (stored === 'dark' || (!stored && prefersDark)) {
        document.documentElement.classList.add('dark');
    }
})();
