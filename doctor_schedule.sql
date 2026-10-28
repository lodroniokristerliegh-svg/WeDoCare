-- WeDoCare: weekly public consultation schedule
-- Run this in the selected InfinityFree database (if0_..._wedocare) or local database.
-- Hours are intentionally not prefilled; Dr. Deguito should enter the clinic's actual schedule.

CREATE TABLE IF NOT EXISTS doctor_schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    weekday TINYINT NOT NULL COMMENT '1=Monday ... 7=Sunday',
    is_available TINYINT(1) NOT NULL DEFAULT 0,
    start_time TIME NULL,
    end_time TIME NULL,
    appointment_mode ENUM('Both','Online','Walk-in') NOT NULL DEFAULT 'Both',
    notes VARCHAR(180) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_doctor_weekday (doctor_id, weekday),
    CONSTRAINT fk_schedule_doctor FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
