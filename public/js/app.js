const imageInput = document.getElementById('image');
const fileLabel = document.getElementById('file-label');
const fileHelp = document.getElementById('file-help');
const previewContainer = document.getElementById('preview-container');
const imagePreview = document.getElementById('image-preview');
const uploadForm = document.getElementById('upload-form');
const submitButton = uploadForm.querySelector('button[type="submit"]');

imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];

    if (!file) {
        return;
    }

    fileLabel.textContent = file.name;
    fileHelp.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;

    if (file.size > 10 * 1024 * 1024) {
        alert('Please choose an image smaller than 10 MB.');
        imageInput.value = '';
        fileLabel.textContent = 'Choose an image to analyse';
        fileHelp.textContent = 'JPEG, PNG, WebP or GIF';
        previewContainer.hidden = true;
        return;
    }

    const previewUrl = URL.createObjectURL(file);
    imagePreview.src = previewUrl;
    previewContainer.hidden = false;
});

uploadForm.addEventListener('submit', () => {
    submitButton.disabled = true;
    submitButton.textContent = 'Uploading...';
});