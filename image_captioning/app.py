import os
import torch
from flask import Flask, render_template, request, jsonify, send_file
from werkzeug.utils import secure_filename
from PIL import Image
import base64
from io import BytesIO
from transformers import VisionEncoderDecoderModel, ViTImageProcessor, AutoTokenizer
import numpy as np
from gtts import gTTS
import uuid

app = Flask(__name__)

# Configuration
UPLOAD_FOLDER = 'uploads'
AUDIO_FOLDER = 'audio'
CAPTION_FOLDER = 'captions'
ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'gif'}

# Create necessary directories
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(AUDIO_FOLDER, exist_ok=True)
os.makedirs(CAPTION_FOLDER, exist_ok=True)

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['AUDIO_FOLDER'] = AUDIO_FOLDER
app.config['CAPTION_FOLDER'] = CAPTION_FOLDER
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024  # 16MB max upload

# Load the pre-trained model
def load_captioning_model():
    model = VisionEncoderDecoderModel.from_pretrained("nlpconnect/vit-gpt2-image-captioning")
    feature_extractor = ViTImageProcessor.from_pretrained("nlpconnect/vit-gpt2-image-captioning")
    tokenizer = AutoTokenizer.from_pretrained("nlpconnect/vit-gpt2-image-captioning")
    
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    model.to(device)
    
    return model, feature_extractor, tokenizer, device

model, feature_extractor, tokenizer, device = load_captioning_model()

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def generate_caption(image_path):
    image = Image.open(image_path).convert('RGB')
    
    # Prepare image for the model
    pixel_values = feature_extractor(images=[image], return_tensors="pt").pixel_values
    pixel_values = pixel_values.to(device)
    
    # Generate caption
    output_ids = model.generate(pixel_values, max_length=16, num_beams=4)
    preds = tokenizer.batch_decode(output_ids, skip_special_tokens=True)
    
    # Return the caption
    caption = preds[0].strip()
    return caption

def generate_audio(caption, file_path):
    tts = gTTS(text=caption, lang='en', slow=False)
    tts.save(file_path)
    return file_path

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/process_image', methods=['POST'])
def process_image():
    if 'files[]' not in request.files:
        return jsonify({'error': 'No files part'}), 400
    
    files = request.files.getlist('files[]')
    results = []
    
    for file in files:
        if file and allowed_file(file.filename):
            # Generate unique filename
            unique_id = str(uuid.uuid4())
            original_filename = secure_filename(file.filename)
            filename_base = os.path.splitext(original_filename)[0]
            
            # Save uploaded image
            image_path = os.path.join(app.config['UPLOAD_FOLDER'], f"{unique_id}_{original_filename}")
            file.save(image_path)
            
            # Generate caption
            caption = generate_caption(image_path)
            
            # Save caption to file
            caption_path = os.path.join(app.config['CAPTION_FOLDER'], f"{filename_base}_{unique_id}.txt")
            with open(caption_path, 'w') as f:
                f.write(caption)
            
            # Generate audio from caption
            audio_path = os.path.join(app.config['AUDIO_FOLDER'], f"{filename_base}_{unique_id}.mp3")
            generate_audio(caption, audio_path)
            
            # Prepare the result
            with open(image_path, "rb") as image_file:
                encoded_image = base64.b64encode(image_file.read()).decode('utf-8')
            
            results.append({
                'id': unique_id,
                'filename': original_filename,
                'image': encoded_image,
                'caption': caption,
                'audio_path': f"/get_audio/{filename_base}_{unique_id}.mp3",
                'caption_path': f"/get_caption/{filename_base}_{unique_id}.txt"
            })
    
    return jsonify({'results': results})

@app.route('/get_audio/<filename>')
def get_audio(filename):
    return send_file(os.path.join(app.config['AUDIO_FOLDER'], filename), as_attachment=True)

@app.route('/get_caption/<filename>')
def get_caption(filename):
    return send_file(os.path.join(app.config['CAPTION_FOLDER'], filename), as_attachment=True)

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
