
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Captioning System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .image-container {
            position: relative;
            margin-bottom: 30px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            display: block;
            margin: 0 auto 15px auto;
        }
        .caption-container {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            color: white;
            font-size: 24px;
        }
        .result-section {
            display: none;
        }
        .dropzone {
            border: 2px dashed #007bff;
            border-radius: 5px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .dropzone:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Image Captioning System</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h3>Upload Images</h3>
            </div>
            <div class="card-body">
                <div id="dropzone" class="dropzone">
                    <p>Drag and drop images here or click to select files</p>
                    <input type="file" id="fileInput" multiple accept="image/*" style="display: none;">
                </div>
                <div id="preview" class="row"></div>
                <button id="processBtn" class="btn btn-primary mt-3" disabled>Process Images</button>
            </div>
        </div>
        
        <div id="resultSection" class="result-section">
            <h2 class="mb-3">Results</h2>
            <div id="results"></div>
        </div>
        
        <div id="loading" class="loading" style="display: none;">
            <div>
                <div class="spinner-border" role="status"></div>
                <div>Processing images... This may take a few moments.</div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('fileInput');
            const preview = document.getElementById('preview');
            const processBtn = document.getElementById('processBtn');
            const loading = document.getElementById('loading');
            const resultSection = document.getElementById('resultSection');
            const resultsContainer = document.getElementById('results');
            
            let files = [];
            
            // Handle drag and drop
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.style.backgroundColor = '#e9ecef';
            });
            
            dropzone.addEventListener('dragleave', () => {
                dropzone.style.backgroundColor = '';
            });
            
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.style.backgroundColor = '';
                
                if (e.dataTransfer.files.length > 0) {
                    handleFiles(e.dataTransfer.files);
                }
            });
            
            // Handle click to select files
            dropzone.addEventListener('click', () => {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', (e) => {
                if (fileInput.files.length > 0) {
                    handleFiles(fileInput.files);
                }
            });
            
            function handleFiles(newFiles) {
                files = [...newFiles];
                preview.innerHTML = '';
                
                if (files.length > 0) {
                    processBtn.disabled = false;
                    
                    // Show preview of selected images
                    files.forEach((file, index) => {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const col = document.createElement('div');
                            col.className = 'col-md-4 mb-3';
                            
                            const card = document.createElement('div');
                            card.className = 'card';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'card-img-top';
                            img.style.maxHeight = '200px';
                            img.style.objectFit = 'contain';
                            
                            const cardBody = document.createElement('div');
                            cardBody.className = 'card-body';
                            
                            const title = document.createElement('p');
                            title.className = 'card-text';
                            title.textContent = file.name;
                            
                            cardBody.appendChild(title);
                            card.appendChild(img);
                            card.appendChild(cardBody);
                            col.appendChild(card);
                            preview.appendChild(col);
                        };
                        
                        reader.readAsDataURL(file);
                    });
                } else {
                    processBtn.disabled = true;
                }
            }
            
            // Process button click handler
            processBtn.addEventListener('click', function() {
                if (files.length === 0) return;
                
                loading.style.display = 'flex';
                
                const formData = new FormData();
                
                files.forEach(file => {
                    formData.append('files[]', file);
                });
                
                fetch('/process_image', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    // Display results
                    resultSection.style.display = 'block';
                    resultsContainer.innerHTML = '';
                    
                    data.results.forEach(result => {
                        const resultDiv = document.createElement('div');
                        resultDiv.className = 'image-container';
                        
                        const img = document.createElement('img');
                        img.src = `data:image/jpeg;base64,${result.image}`;
                        img.className = 'preview-image';
                        
                        const captionDiv = document.createElement('div');
                        captionDiv.className = 'caption-container';
                        captionDiv.innerHTML = `<strong>Caption:</strong> ${result.caption}`;
                        
                        const audio = document.createElement('audio');
                        audio.controls = true;
                        audio.autoplay = true;
                        audio.src = result.audio_path;
                        audio.className = 'mb-3 w-100';
                        
                        const downloadRow = document.createElement('div');
                        downloadRow.className = 'row';
                        
                        const audioCol = document.createElement('div');
                        audioCol.className = 'col-md-6 mb-2';
                        
                        const captionCol = document.createElement('div');
                        captionCol.className = 'col-md-6 mb-2';
                        
                        const downloadAudio = document.createElement('a');
                        downloadAudio.href = result.audio_path;
                        downloadAudio.className = 'btn btn-success w-100';
                        downloadAudio.textContent = 'Download Audio';
                        downloadAudio.setAttribute('download', '');
                        
                        const downloadCaption = document.createElement('a');
                        downloadCaption.href = result.caption_path;
                        downloadCaption.className = 'btn btn-info w-100';
                        downloadCaption.textContent = 'Download Caption';
                        downloadCaption.setAttribute('download', '');
                        
                        audioCol.appendChild(downloadAudio);
                        captionCol.appendChild(downloadCaption);
                        downloadRow.appendChild(audioCol);
                        downloadRow.appendChild(captionCol);
                        
                        resultDiv.appendChild(img);
                        resultDiv.appendChild(captionDiv);
                        resultDiv.appendChild(audio);
                        resultDiv.appendChild(downloadRow);
                        
                        resultsContainer.appendChild(resultDiv);
                    });
                    
                    // Scroll to results
                    resultSection.scrollIntoView({ behavior: 'smooth' });
                })
                .catch(error => {
                    loading.style.display = 'none';
                    alert('Error: ' + error.message);
                });
            });
        });
    </script>
</body>
</html>