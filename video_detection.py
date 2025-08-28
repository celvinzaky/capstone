import os
import cv2
import time
import pandas as pd
from datetime import datetime
from ultralytics import YOLO
import mysql.connector

# -------------------------------
# KONFIGURASI
# -------------------------------

INPUT_FOLDER = "C:/xampp/htdocs/employee_monitoring/uploads"
OUTPUT_FOLDER = "C:/xampp/htdocs/employee_monitoring/uploads_processing"
ALERT_FOLDER = "C:/xampp/htdocs/employee_monitoring/alerts"
EXCEL_LOG = "C:/xampp/htdocs/employee_monitoring/activity_log.xlsx"

DETECTION_INTERVAL = 120  # dalam detik (2 menit)
YOLO_MODEL_PATH = "runs/detect/train14/weights/best.pt"
CONFIDENCE_THRESHOLD = 0.6

# -------------------------------
# SETUP AWAL
# -------------------------------

os.makedirs(OUTPUT_FOLDER, exist_ok=True)
os.makedirs(ALERT_FOLDER, exist_ok=True)

# Koneksi ke database MySQL
db = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="employee_monitoring"
)
cursor = db.cursor()

# Load model YOLO
model = YOLO(YOLO_MODEL_PATH)

# -------------------------------
# FUNGSI: Proses Gambar
# -------------------------------

def process_image(image_path, output_path):
    frame = cv2.imread(image_path)
    if frame is None:
        print(f"[ERROR] Gagal membaca gambar: {image_path}")
        return None

    results = model.predict(frame, conf=CONFIDENCE_THRESHOLD)

    working_count = 0
    not_working_count = 0
    annotated_frame = frame.copy()

    for box in results[0].boxes:
        x1, y1, x2, y2 = map(int, box.xyxy[0])
        class_id = int(box.cls)
        label = model.names[class_id]

        if label == "Working":
            color = (0, 255, 0)
            working_count += 1
        elif label == "Notworking":
            color = (0, 0, 255)
            not_working_count += 1
        else:
            color = (255, 255, 255)

        cv2.rectangle(annotated_frame, (x1, y1), (x2, y2), color, 2)
        cv2.putText(annotated_frame, label, (x1, y1 - 10),
                    cv2.FONT_HERSHEY_SIMPLEX, 1, color, 2)

    cv2.putText(annotated_frame,
                f"Working: {working_count} | Not Working: {not_working_count}",
                (10, 30),
                cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)

    if not_working_count > 0:
        cv2.rectangle(annotated_frame,
                      (0, 0),
                      (frame.shape[1] - 1, frame.shape[0] - 1),
                      (0, 0, 255), 10)
        alert_path = os.path.join(ALERT_FOLDER, f"alert_{os.path.basename(image_path)}")
        cv2.imwrite(alert_path, annotated_frame)

    cv2.imwrite(output_path, annotated_frame)

    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    sql = """
        INSERT INTO activity_logs (timestamp, image_path, working_count, not_working_count, total_employees)
        VALUES (%s, %s, %s, %s, %s)
    """
    values = (timestamp, os.path.basename(image_path), working_count, not_working_count, len(results[0].boxes))
    cursor.execute(sql, values)
    db.commit()

    return {
        'timestamp': timestamp,
        'image_path': os.path.basename(image_path),
        'working_count': working_count,
        'not_working_count': not_working_count,
        'total_employees': len(results[0].boxes)
    }

# -------------------------------
# FUNGSI: Proses Semua Gambar
# -------------------------------

def process_all_images():
    activity_log = []

    image_files = [f for f in os.listdir(INPUT_FOLDER)
                   if f.lower().endswith(('.png', '.jpg', '.jpeg'))]

    if not image_files:
        return

    print(f"[SCAN] Menemukan {len(image_files)} gambar untuk diproses...")

    for img_file in image_files:
        input_path = os.path.join(INPUT_FOLDER, img_file)
        output_path = os.path.join(OUTPUT_FOLDER, f"detected_{img_file}")
        log_entry = process_image(input_path, output_path)

        if log_entry:
            activity_log.append(log_entry)
            print(f"-> Diproses: {img_file} | Working: {log_entry['working_count']}, Notworking: {log_entry['not_working_count']}")

        os.remove(input_path)

    if activity_log:
        df = pd.DataFrame(activity_log)
        if os.path.exists(EXCEL_LOG):
            old_df = pd.read_excel(EXCEL_LOG)
            df = pd.concat([old_df, df], ignore_index=True)
        df.to_excel(EXCEL_LOG, index=False)
        print(f"[LOG] Log aktivitas disimpan ke {EXCEL_LOG}")

# -------------------------------
# FUNGSI: Main Loop
# -------------------------------

def main():
    print("[INFO] Sistem monitoring dimulai...")
    last_detection_time = time.time()

    try:
        while True:
            now = time.time()

            # Cek jika sudah lewat 2 menit (interval)
            if now - last_detection_time >= DETECTION_INTERVAL:
                print(f"\n[INTERVAL] Pemeriksaan rutin setiap {DETECTION_INTERVAL} detik.")
                process_all_images()
                last_detection_time = now

            # Real-time check: apakah ada gambar baru?
            image_files = [f for f in os.listdir(INPUT_FOLDER)
                           if f.lower().endswith(('.png', '.jpg', '.jpeg'))]

            if image_files:
                print(f"\n[REAL-TIME] Deteksi gambar baru ({len(image_files)} file)...")
                process_all_images()
                last_detection_time = time.time()  # reset waktu setelah proses

            time.sleep(1)  # cek folder setiap 1 detik

    except KeyboardInterrupt:
        print("\n[INFO] Program dihentikan oleh pengguna.")
    except Exception as e:
        print(f"[ERROR] Terjadi kesalahan: {e}")
    finally:
        cursor.close()
        db.close()

# -------------------------------
# ENTRY POINT
# -------------------------------

if __name__ == "__main__":
    main()
