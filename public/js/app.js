const imageInput = document.getElementById('image');
const fileLabel = document.getElementById('file-label');
const fileHelp = document.getElementById('file-help');
const previewContainer = document.getElementById('preview-container');
const imagePreview = document.getElementById('image-preview');
const uploadForm = document.getElementById('upload-form');
const submitButton = uploadForm.querySelector('button[type="submit"]');
const maxBytes = Number(imageInput.dataset.maxBytes) || 10 * 1024 * 1024;
const defaultLabel = 'Choose an image to analyse';
const defaultHelp = 'JPEG, PNG, WebP or GIF';

let previewUrl = null;

function resetPicker() {
    imageInput.value = '';
    fileLabel.textContent = defaultLabel;
    fileHelp.textContent = defaultHelp;
    previewContainer.hidden = true;
    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    }
}

imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];

    if (!file) {
        return;
    }

    if (file.size > maxBytes) {
        alert(`Please choose an image smaller than ${Math.floor(maxBytes / 1024 / 1024)} MB.`);
        resetPicker();
        return;
    }

    fileLabel.textContent = file.name;
    fileHelp.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;

    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
    }
    previewUrl = URL.createObjectURL(file);
    imagePreview.src = previewUrl;
    previewContainer.hidden = false;
});

uploadForm.addEventListener('submit', () => {
    submitButton.disabled = true;
    submitButton.textContent = 'Analysing… this can take a few seconds';
});
