document.querySelectorAll('[data-open-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.openDialog);
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
        }
    });
});

document.querySelectorAll('[data-close-dialog]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
});

const searchInput = document.querySelector('.search-field input');
const filterForm = document.querySelector('.filter-form');
let searchTimer;

if (searchInput && filterForm) {
    searchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => filterForm.requestSubmit(), 350);
    });
}
