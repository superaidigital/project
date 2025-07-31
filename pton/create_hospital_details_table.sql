
-- สร้างตารางเก็บข้อมูลรายละเอียดสำหรับ รพ.สต.
CREATE TABLE hospital_daily_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shelter_id INT NOT NULL,
    report_date DATE NOT NULL,
    
    -- จำนวนผู้เข้าพักวันนี้ (แยกตามเพศ)
    total_patients INT DEFAULT 0,
    male_patients INT DEFAULT 0,
    female_patients INT DEFAULT 0,
    pregnant_women INT DEFAULT 0,
    
    -- กลุ่มผู้ป่วยพิเศษ
    disabled_patients INT DEFAULT 0,
    bedridden_patients INT DEFAULT 0,
    elderly_patients INT DEFAULT 0,
    child_patients INT DEFAULT 0,
    
    -- โรคเรื้อรัง
    chronic_disease_patients INT DEFAULT 0,
    diabetes_patients INT DEFAULT 0,
    hypertension_patients INT DEFAULT 0,
    heart_disease_patients INT DEFAULT 0,
    mental_health_patients INT DEFAULT 0,
    kidney_disease_patients INT DEFAULT 0,
    other_monitored_diseases INT DEFAULT 0,
    
    -- บันทึกเพิ่มเติม
    notes TEXT,
    
    -- การติดตาม
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    
    -- Index และ Constraints
    UNIQUE KEY unique_shelter_date (shelter_id, report_date),
    FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
    INDEX idx_report_date (report_date),
    INDEX idx_shelter_date (shelter_id, report_date)
);

-- เพิ่มคอลัมน์ในตาราง shelters เพื่อบอกว่าต้องการข้อมูลรายละเอียดหรือไม่
ALTER TABLE shelters ADD COLUMN requires_detailed_report BOOLEAN DEFAULT FALSE;

-- อัพเดต รพ.สต. ให้ต้องการข้อมูลรายละเอียด
UPDATE shelters SET requires_detailed_report = TRUE WHERE type = 'รพ.สต.';
