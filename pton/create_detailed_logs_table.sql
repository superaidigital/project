-- สร้างตารางเก็บ log การอัปเดตแบบละเอียดสำหรับศูนย์พักพิงและ รพ.สต.
CREATE TABLE hospital_update_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shelter_id INT NOT NULL,
    operation_type ENUM('add', 'subtract') NOT NULL,
    report_date DATE NOT NULL,
    
    -- ข้อมูลก่อนอัปเดต
    old_total_patients INT DEFAULT 0,
    old_male_patients INT DEFAULT 0,
    old_female_patients INT DEFAULT 0,
    old_pregnant_women INT DEFAULT 0,
    old_disabled_patients INT DEFAULT 0,
    old_bedridden_patients INT DEFAULT 0,
    old_elderly_patients INT DEFAULT 0,
    old_child_patients INT DEFAULT 0,
    old_chronic_disease_patients INT DEFAULT 0,
    old_diabetes_patients INT DEFAULT 0,
    old_hypertension_patients INT DEFAULT 0,
    old_heart_disease_patients INT DEFAULT 0,
    old_mental_health_patients INT DEFAULT 0,
    old_kidney_disease_patients INT DEFAULT 0,
    old_other_monitored_diseases INT DEFAULT 0,
    
    -- ข้อมูลที่เปลี่ยนแปลง (จำนวนที่เพิ่ม/ลด)
    change_total_patients INT DEFAULT 0,
    change_male_patients INT DEFAULT 0,
    change_female_patients INT DEFAULT 0,
    change_pregnant_women INT DEFAULT 0,
    change_disabled_patients INT DEFAULT 0,
    change_bedridden_patients INT DEFAULT 0,
    change_elderly_patients INT DEFAULT 0,
    change_child_patients INT DEFAULT 0,
    change_chronic_disease_patients INT DEFAULT 0,
    change_diabetes_patients INT DEFAULT 0,
    change_hypertension_patients INT DEFAULT 0,
    change_heart_disease_patients INT DEFAULT 0,
    change_mental_health_patients INT DEFAULT 0,
    change_kidney_disease_patients INT DEFAULT 0,
    change_other_monitored_diseases INT DEFAULT 0,
    
    -- ข้อมูลหลังอัปเดต
    new_total_patients INT DEFAULT 0,
    new_male_patients INT DEFAULT 0,
    new_female_patients INT DEFAULT 0,
    new_pregnant_women INT DEFAULT 0,
    new_disabled_patients INT DEFAULT 0,
    new_bedridden_patients INT DEFAULT 0,
    new_elderly_patients INT DEFAULT 0,
    new_child_patients INT DEFAULT 0,
    new_chronic_disease_patients INT DEFAULT 0,
    new_diabetes_patients INT DEFAULT 0,
    new_hypertension_patients INT DEFAULT 0,
    new_heart_disease_patients INT DEFAULT 0,
    new_mental_health_patients INT DEFAULT 0,
    new_kidney_disease_patients INT DEFAULT 0,
    new_other_monitored_diseases INT DEFAULT 0,
    
    -- ข้อมูลเพิ่มเติม
    notes TEXT,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    -- เวลา
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Index และ Constraints
    FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
    INDEX idx_shelter_date (shelter_id, report_date),
    INDEX idx_operation_type (operation_type),
    INDEX idx_created_at (created_at)
);
