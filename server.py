from flask import Flask, request, jsonify
import os
from ultralytics import YOLO
import requests

app = Flask(__name__)
UPLOAD_FOLDER = "uploads"
DETECTED_FOLDER = "detected"
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(DETECTED_FOLDER, exist_ok=True)

model = YOLO("yolov8n.pt")  # model YOLO

@app.route("/upload", methods=["POST"])
def upload():
    file = request.data
    filename = "capture.jpg"
    filepath = os.path.join(UPLOAD_FOLDER, filename)
    with open(filepath, "wb") as f:
        f.write(file)

    # YOLO deteksi
    results = model(filepath, save=True, project=DETECTED_FOLDER, name="runs", exist_ok=True)
    detected_path = results[0].save_dir + "/" + filename

    # Kirim ke PHP server jika ada objek terdeteksi
    if results[0].boxes:
        files = {'image': open(detected_path, 'rb')}
        data = {'description': 'Violation detected'}
        requests.post("http://10.39.183.200:5000/employee_monitoring/upload_from_flask.php", data=data, files=files)

    return jsonify({"status": "processed", "detected": len(results[0].boxes),})

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)