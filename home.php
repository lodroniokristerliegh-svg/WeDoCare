<?php
require_once __DIR__ . '/config.php';

// Public landing page — no login required. Pulls the doctor's own
// profile (photo, credentials, bio) so the homepage always reflects
// whatever is filled in under Settings.
try {
    $doc = $pdo->query("SELECT * FROM users WHERE role='doctor' LIMIT 1")->fetch();
} catch (PDOException $e) {
    $doc = null;
}
$docName  = isset($doc['full_name']) ? $doc['full_name'] : 'Dr. Kendrick Kerk Deguito';
$docSpec  = isset($doc['specialization']) ? $doc['specialization'] : 'General Practitioner';
$docLic   = isset($doc['license_no']) ? $doc['license_no'] : '';
$docAddr  = isset($doc['clinic_address']) ? $doc['clinic_address'] : 'Surigao City, Surigao del Norte, Philippines';
$docPhone = isset($doc['contact_no']) ? $doc['contact_no'] : '';
$docBio   = isset($doc['bio']) ? $doc['bio'] : 'Dedicated to providing accessible, quality healthcare for every patient — whether you visit in person or consult online.';
$docPhoto = isset($doc['photo']) ? $doc['photo'] : '';
$docFirst = explode(' ', str_replace('Dr. ', '', $docName))[0];

// Homepage images are intentionally SEPARATE from the doctor's account
// profile photo (Settings > Profile Photo). Changing the profile photo
// does not affect the homepage. Replace these files in /assets to change them.
$heroImg  = 'assets/picture1.jpg';
$aboutImg = 'assets/picture2.png';

// No testimonials/reviews table exists yet in this project — do not
// fabricate reviews. When one exists, populate $testimonials here and
// the section below will render dynamically instead of the empty state.
$testimonials = [];

// Public weekly schedule maintained by the doctor in Settings.
// Until the doctor publishes hours, the homepage clearly says so rather than guessing.
$weeklySchedule = [];
try {
    if (!empty($doc['user_id'])) {
        $schedStmt = $pdo->prepare("SELECT weekday, is_available, start_time, end_time, appointment_mode, notes
                                    FROM doctor_schedule WHERE doctor_id = ? ORDER BY weekday");
        $schedStmt->execute([(int)$doc['user_id']]);
        $weeklySchedule = $schedStmt->fetchAll();
    }
} catch (PDOException $e) { $weeklySchedule = []; }
$scheduleByDay = [];
foreach ($weeklySchedule as $scheduleRow) { $scheduleByDay[(int)$scheduleRow['weekday']] = $scheduleRow; }
$weekdayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$hasPublishedSchedule = false;
foreach ($scheduleByDay as $scheduleRow) {
    if (!empty($scheduleRow['is_available']) && !empty($scheduleRow['start_time']) && !empty($scheduleRow['end_time'])) {
        $hasPublishedSchedule = true; break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WEDOCARE — <?= htmlspecialchars($docName) ?></title>
<meta name="description" content="WEDOCARE is <?= htmlspecialchars($docName) ?>'s digital healthcare platform — book appointments, consult online, and manage your care in one place.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* =========================================================
   WEDOCARE — Design tokens
   ========================================================= */
:root{
  --blue:#0878D1;
  --blue-dark:#0665B0;
  --navy:#14213D;
  --light-blue:#EAF6FF;
  --soft-cyan:#E8FBF8;
  --green:#35D39A;
  --white:#FFFFFF;
  --bg:#F7F9FC;
  --text:#25324A;
  --text-muted:#647089;
  --border:#E3E9F2;
  --radius-lg:28px;
  --radius-md:20px;
  --radius-sm:14px;
  --shadow-sm:0 8px 24px -12px rgba(20,33,61,0.14);
  --shadow-md:0 22px 48px -20px rgba(8,120,209,0.28);
  --shadow-lg:0 34px 60px -26px rgba(20,33,61,0.32);
}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{
  font-family:'Inter',-apple-system,'Segoe UI',sans-serif;
  color:var(--text);
  background:var(--white);
  line-height:1.6;
  -webkit-font-smoothing:antialiased;
  overflow-x:hidden;
}
a{color:inherit;text-decoration:none;}
h1,h2,h3,h4{font-family:'Poppins',sans-serif;line-height:1.18;letter-spacing:-0.015em;color:var(--navy);}
img{max-width:100%;display:block;}
button{font-family:inherit;cursor:pointer;}

.wrap{max-width:1240px;margin:0 auto;padding:0 32px;}
section{padding:88px 0;}

/* Small label above headings */
.eyebrow{
  display:inline-flex;align-items:center;gap:8px;
  font-size:13px;font-weight:600;color:var(--blue);
  letter-spacing:0.02em;text-transform:uppercase;
}
.eyebrow .dot{width:6px;height:6px;border-radius:50%;background:var(--green);}

.section-head{max-width:620px;margin-bottom:48px;}
.section-head.center{margin-left:auto;margin-right:auto;text-align:center;}
.section-head h2{font-size:clamp(26px,3.2vw,38px);font-weight:700;color:var(--navy);margin-top:12px;}
.section-head p{margin-top:14px;color:var(--text-muted);font-size:16px;}

/* =========================================================
   Buttons
   ========================================================= */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:14px 28px;border-radius:999px;font-weight:600;font-size:14.5px;
  border:none;transition:transform .16s ease, box-shadow .16s ease, background .16s ease, color .16s ease, border-color .16s ease;
  white-space:nowrap;
}
.btn-primary{background:var(--blue);color:#fff;box-shadow:0 12px 26px -10px rgba(8,120,209,0.6);}
.btn-primary:hover{background:var(--blue-dark);transform:translateY(-2px);box-shadow:0 16px 30px -10px rgba(8,120,209,0.7);}
.btn-outline{background:transparent;color:var(--navy);border:1.5px solid #CBD6E4;}
.btn-outline:hover{border-color:var(--blue);color:var(--blue);transform:translateY(-2px);}
.btn-white{background:#fff;color:var(--blue);box-shadow:0 12px 26px -12px rgba(0,0,0,0.25);}
.btn-white:hover{background:var(--light-blue);transform:translateY(-2px);}
.btn-outline-light{background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,0.55);}
.btn-outline-light:hover{background:rgba(255,255,255,0.14);transform:translateY(-2px);}

/* =========================================================
   Header / Nav
   ========================================================= */
header.site-header{
  position:sticky;top:0;z-index:1000;background:rgba(255,255,255,0.92);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border-bottom:1px solid transparent;transition:border-color .2s ease, box-shadow .2s ease;
}
header.site-header.scrolled{border-bottom-color:var(--border);box-shadow:0 2px 18px -8px rgba(20,33,61,0.12);}
.nav-inner{max-width:1440px;margin:0 auto;padding:14px clamp(18px,3vw,40px);display:flex;align-items:center;justify-content:space-between;gap:clamp(14px,2vw,28px);}
.logo{flex:0 0 auto;min-width:0;gap:11px;font-size:21px;}
.logo-image{display:block;width:48px;height:48px;object-fit:contain;flex-shrink:0;}
.logo-copy{display:flex;flex-direction:column;min-width:0;line-height:1.2;}
.logo-name{font-family:'Poppins',sans-serif;font-size:21px;font-weight:800;letter-spacing:-.04em;color:var(--blue);white-space:nowrap;}
.logo-sub{font-size:10px;line-height:1.35;max-width:245px;white-space:normal;letter-spacing:.025em;}
.nav-links{gap:clamp(14px,1.8vw,26px);flex:1;justify-content:center;min-width:0;}
.nav-links a{white-space:nowrap;font-size:14px;}
.nav-actions{flex-shrink:0;gap:9px;}
.nav-actions .btn{padding:11px 20px;font-size:14px;}
.logo{display:flex;align-items:center;gap:10px;font-family:'Poppins',sans-serif;font-weight:800;font-size:21px;color:var(--navy);}
.logo-mark{width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,var(--blue),#3FB6E8);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 8px 18px -8px rgba(8,120,209,0.6);}
.logo-mark svg{width:19px;height:19px;}
.logo span{color:var(--blue);}
.logo-sub{display:block;font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;color:var(--text-muted);letter-spacing:0.04em;text-transform:uppercase;margin-top:2px;}
.nav-links{display:flex;align-items:center;gap:34px;}
.nav-links a{font-size:14.5px;font-weight:500;color:var(--text);position:relative;padding:4px 0;transition:color .16s ease;}
.nav-links a::after{content:'';position:absolute;left:0;right:0;bottom:-2px;height:2px;border-radius:2px;background:var(--blue);transform:scaleX(0);transform-origin:left;transition:transform .2s ease;}
.nav-links a:hover{color:var(--blue);}
.nav-links a:hover::after{transform:scaleX(1);}
.nav-actions{display:flex;align-items:center;gap:12px;}
.hamburger{display:none;flex-direction:column;gap:5px;background:none;border:none;padding:6px;}
.hamburger span{width:22px;height:2px;background:var(--navy);border-radius:2px;transition:transform .25s ease, opacity .25s ease;}
.hamburger.active span:nth-child(1){transform:translateY(7px) rotate(45deg);}
.hamburger.active span:nth-child(2){opacity:0;}
.hamburger.active span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}
.mobile-menu{display:none;flex-direction:column;background:#fff;border-bottom:1px solid var(--border);padding:8px 32px 24px;}
.mobile-menu.open{display:flex;}
.mobile-menu a{padding:14px 0;border-bottom:1px solid var(--border);font-weight:500;font-size:15px;}
.mobile-menu .mobile-actions{display:flex;flex-direction:column;gap:12px;margin-top:16px;}
.mobile-actions .btn{width:100%;}

/* =========================================================
   Hero
   ========================================================= */
.hero-wrap{padding:40px 0 64px;}
.hero{
  position:relative;
  background:
    radial-gradient(circle at 92% 10%, rgba(53,211,154,0.16), transparent 42%),
    linear-gradient(120deg,var(--light-blue) 0%, var(--soft-cyan) 62%, #F2FCFA 100%);
  border-radius:var(--radius-lg);
  padding:64px 56px 122px;
  display:grid;grid-template-columns:1.05fr 0.95fr;gap:44px;align-items:center;overflow:visible;
  animation:riseIn .7s cubic-bezier(.16,1,.3,1) both;
}
.hero::before{content:'';position:absolute;width:420px;height:420px;border-radius:50%;
  background:radial-gradient(circle,rgba(8,120,209,0.14),transparent 70%);top:-150px;right:-100px;}
.hero::after{content:'';position:absolute;width:280px;height:280px;border-radius:50%;
  background:radial-gradient(circle,rgba(53,211,154,0.18),transparent 70%);bottom:-120px;left:-80px;}
@keyframes riseIn{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
.hero-content{position:relative;z-index:2;}
.hero-content h1{font-size:clamp(34px,4.6vw,52px);font-weight:800;color:var(--navy);margin-top:16px;}
.hero-content p.lead{margin-top:20px;font-size:17px;color:var(--text-muted);max-width:480px;}
.hero-actions{margin-top:32px;display:flex;gap:14px;flex-wrap:wrap;}
.hero-visual{position:relative;z-index:2;display:flex;justify-content:center;}
.doctor-frame{
  position:relative;width:100%;max-width:400px;aspect-ratio:3/3.6;border-radius:26px;overflow:hidden;
  box-shadow:var(--shadow-lg);
}
.doctor-frame img{width:100%;height:100%;object-fit:cover;}
.doctor-frame .ph-placeholder{
  width:100%;height:100%;background:linear-gradient(155deg,var(--blue),#3FB6E8);
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;text-align:center;padding:30px;
}
.doctor-frame .ph-placeholder svg{width:52px;height:52px;stroke:rgba(255,255,255,0.85);fill:none;stroke-width:1.4;}
.doctor-frame .ph-placeholder p{color:rgba(255,255,255,0.85);font-size:13.5px;max-width:220px;}

/* Trust strip (real system features only — no fake statistics) */
.trust-strip{
  position:absolute;left:56px;right:56px;bottom:-42px;z-index:5;
  background:rgba(255,255,255,0.82);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  border:1px solid rgba(255,255,255,0.7);border-radius:var(--radius-md);
  box-shadow:0 22px 48px -18px rgba(20,33,61,0.22);padding:24px 32px;
  display:grid;grid-template-columns:repeat(4,1fr);gap:20px;
}
.trust-item{display:flex;align-items:center;gap:12px;}
.trust-item .ico{width:44px;height:44px;border-radius:12px;background:var(--light-blue);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.trust-item .ico svg{width:20px;height:20px;stroke:var(--blue);fill:none;stroke-width:1.7;}
.trust-item h4{font-family:'Inter',sans-serif;font-size:14px;font-weight:700;color:var(--navy);}
.trust-item span{font-size:12.5px;color:var(--text-muted);}

/* =========================================================
   How it works
   ========================================================= */
.steps-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;}
.step-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-md);padding:34px 28px;
  transition:box-shadow .2s ease, transform .2s ease, border-color .2s ease;}
.step-card:hover{box-shadow:var(--shadow-md);transform:translateY(-4px);border-color:transparent;}
.step-icon{width:54px;height:54px;border-radius:14px;background:var(--light-blue);display:flex;align-items:center;justify-content:center;margin-bottom:20px;}
.step-icon svg{width:24px;height:24px;stroke:var(--blue);fill:none;stroke-width:1.7;}
.step-num{font-size:12.5px;font-weight:700;color:var(--blue);letter-spacing:0.06em;margin-bottom:8px;text-transform:uppercase;}
.step-card h3{font-size:17px;font-weight:700;color:var(--navy);margin-bottom:10px;}
.step-card p{font-size:14.5px;color:var(--text-muted);}

/* Doctor schedule */
.schedule-section{background:linear-gradient(180deg,#F7FAFE 0%,#FFFFFF 100%);}
.schedule-panel{display:grid;grid-template-columns:.78fr 1.22fr;gap:34px;align-items:stretch;}
.schedule-intro{background:linear-gradient(145deg,#0B3D6B,#0878D1);color:#fff;border-radius:24px;padding:34px;position:relative;overflow:hidden;}
.schedule-intro:after{content:'+';position:absolute;right:18px;bottom:-68px;font-size:220px;line-height:1;color:rgba(255,255,255,.07);font-weight:700;}
.schedule-intro .eyebrow{color:#9FE9D2;}
.schedule-intro h3{color:#fff;font-size:27px;margin-top:14px;}
.schedule-intro p{color:#D7E8F8;font-size:14.5px;margin-top:14px;position:relative;z-index:1;}
.schedule-intro .btn{margin-top:24px;position:relative;z-index:1;}
.schedule-card{background:#fff;border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow-sm);padding:26px;}
.schedule-card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px;}
.schedule-card-head h3{font-size:19px;}
.schedule-status{font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border-radius:999px;padding:6px 10px;background:#E8F8F1;color:#16734A;}
.schedule-list{display:grid;gap:0;}
.schedule-row{display:grid;grid-template-columns:1fr 1.2fr;align-items:center;gap:14px;padding:13px 0;border-bottom:1px solid #EDF1F6;}
.schedule-row:last-child{border-bottom:0;}
.schedule-day{font-size:14px;font-weight:700;color:var(--navy);}
.schedule-time{font-size:13px;color:var(--text-muted);text-align:right;}
.schedule-time strong{display:block;color:var(--text);font-size:13px;}
.schedule-mode{display:block;font-size:11px;color:var(--blue);margin-top:2px;}
.schedule-closed{color:#8A94A8;}
.schedule-note{margin-top:16px;padding:12px 14px;background:#F4F8FC;border-radius:12px;color:var(--text-muted);font-size:12.5px;}
@media(max-width:850px){.schedule-panel{grid-template-columns:1fr;}.schedule-intro{padding:28px;}}
@media(max-width:560px){.schedule-card{padding:18px;}.schedule-row{grid-template-columns:.8fr 1.2fr;gap:8px}.schedule-day{font-size:13px}.schedule-time{font-size:12px}}

/* =========================================================
   Why choose
   ========================================================= */
.why-section{background:var(--bg);}
.why-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:22px;}
.why-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-md);padding:30px 26px;text-align:left;
  transition:box-shadow .2s ease, transform .2s ease, border-color .2s ease;}
.why-card:hover{box-shadow:var(--shadow-md);transform:translateY(-4px);border-color:transparent;}
.why-icon{width:48px;height:48px;border-radius:12px;background:rgba(53,211,154,0.14);display:flex;align-items:center;justify-content:center;margin-bottom:20px;}
.why-icon svg{width:22px;height:22px;stroke:var(--green);fill:none;stroke-width:1.8;}
.why-card h3{font-size:16px;font-weight:700;color:var(--navy);margin-bottom:8px;}
.why-card p{font-size:14px;color:var(--text-muted);}

/* =========================================================
   Services
   ========================================================= */
.services-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;}
.service-card{border:1px solid var(--border);border-radius:var(--radius-md);padding:30px 26px;
  transition:box-shadow .2s ease,transform .2s ease,border-color .2s ease;background:#fff;}
.service-card:hover{box-shadow:var(--shadow-md);transform:translateY(-4px);border-color:transparent;}
.service-icon{width:46px;height:46px;border-radius:12px;background:var(--light-blue);display:flex;align-items:center;justify-content:center;margin-bottom:18px;}
.service-icon svg{width:21px;height:21px;stroke:var(--blue);fill:none;stroke-width:1.8;}
.service-card h3{font-size:15.5px;font-weight:700;color:var(--navy);margin-bottom:8px;}
.service-card p{font-size:13.8px;color:var(--text-muted);}

/* =========================================================
   Payment methods (existing feature, restyled)
   ========================================================= */
.pay-section{background:var(--bg);}
.pay-row{display:flex;justify-content:center;gap:56px;flex-wrap:wrap;margin-top:8px;}
.pay-item{text-align:center;}
.pay-badge{width:78px;height:78px;border-radius:50%;background:#fff;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:var(--shadow-sm);}
.pay-item .label{font-size:13.5px;font-weight:700;}
.pay-note{text-align:center;margin-top:28px;font-size:13px;color:var(--text-muted);}

/* =========================================================
   Doctor section
   ========================================================= */
.doctor-section{background:var(--navy);color:#fff;position:relative;overflow:hidden;}
.doctor-section::before{content:'';position:absolute;width:380px;height:380px;border-radius:50%;
  background:radial-gradient(circle,rgba(8,120,209,0.4),transparent 70%);bottom:-170px;left:-110px;}
.doctor-section::after{content:'';position:absolute;width:300px;height:300px;border-radius:50%;
  background:radial-gradient(circle,rgba(53,211,154,0.2),transparent 70%);top:-120px;right:-80px;}
.doctor-grid{display:grid;grid-template-columns:0.85fr 1.15fr;gap:56px;align-items:center;position:relative;z-index:2;}
.about-photo-wrap{position:relative;width:100%;aspect-ratio:1/1.1;border-radius:var(--radius-lg);overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,0.35);}
.about-photo-wrap img{width:100%;height:100%;object-fit:cover;}
.about-photo-wrap .ph-placeholder{width:100%;height:100%;background:linear-gradient(155deg,var(--blue),#3FB6E8);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;text-align:center;padding:30px;}
.about-photo-wrap .ph-placeholder svg{width:44px;height:44px;stroke:rgba(255,255,255,0.85);fill:none;stroke-width:1.4;}
.about-photo-wrap .ph-placeholder p{color:rgba(255,255,255,0.85);font-size:13px;max-width:200px;}
.doctor-info h2{font-size:clamp(24px,3vw,34px);font-weight:700;margin-top:10px;color:#fff;}
.doctor-info .eyebrow{color:#7FCBFF;}
.doctor-info .role{font-size:14.5px;color:#9FB2CE;margin-top:6px;}
.doctor-bio{margin-top:22px;font-size:15px;color:#C5D2E6;max-width:560px;}
.doctor-facts{margin-top:26px;display:flex;flex-direction:column;gap:14px;max-width:560px;}
.doctor-fact{display:flex;gap:14px;font-size:13.5px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.14);}
.doctor-fact:last-child{border-bottom:none;padding-bottom:0;}
.doctor-fact-label{color:rgba(255,255,255,0.5);min-width:150px;flex-shrink:0;}
.doctor-fact-value{color:#fff;font-weight:500;}
.doctor-info .btn{margin-top:32px;}

/* =========================================================
   Patient experience / testimonials
   ========================================================= */
.testimonial-empty{
  border:1.5px dashed #D6DEE8;border-radius:var(--radius-md);padding:56px 32px;text-align:center;
  max-width:620px;margin:0 auto;background:var(--bg);
}
.testimonial-empty .icon{width:56px;height:56px;border-radius:50%;background:var(--light-blue);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;}
.testimonial-empty .icon svg{width:24px;height:24px;stroke:var(--blue);fill:none;stroke-width:1.7;}
.testimonial-empty h3{font-size:18px;font-weight:700;color:var(--navy);margin-bottom:8px;}
.testimonial-empty p{font-size:14.5px;color:var(--text-muted);}
.testimonials-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;}
.testimonial-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-md);padding:28px 26px;}
.testimonial-card .stars{color:var(--green);font-size:13px;margin-bottom:12px;}
.testimonial-card p.body-text{font-size:14.5px;color:var(--text-muted);}
.testimonial-footer{margin-top:20px;display:flex;align-items:center;gap:12px;padding-top:16px;border-top:1px solid var(--border);}
.avatar-ph{width:38px;height:38px;border-radius:50%;background:var(--light-blue);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--blue);}
.testimonial-footer span{font-size:13px;font-weight:600;color:var(--navy);}

/* =========================================================
   Health info
   ========================================================= */
.health-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;}
.health-card{position:relative;border:1px solid var(--border);border-radius:var(--radius-md);padding:30px 26px;overflow:hidden;background:#fff;}
.health-badge{position:absolute;top:20px;right:20px;font-size:11px;font-weight:700;letter-spacing:0.04em;color:var(--blue);background:var(--light-blue);padding:5px 12px;border-radius:999px;}
.health-icon{width:46px;height:46px;border-radius:12px;background:rgba(53,211,154,0.14);display:flex;align-items:center;justify-content:center;margin-bottom:20px;}
.health-icon svg{width:21px;height:21px;stroke:var(--green);fill:none;stroke-width:1.8;}
.health-card h3{font-size:16px;font-weight:700;color:var(--navy);margin-bottom:8px;}
.health-card p{font-size:13.5px;color:var(--text-muted);}

/* =========================================================
   FAQ
   ========================================================= */
.faq-section{background:var(--bg);}
.faq-list{max-width:780px;margin:0 auto;display:flex;flex-direction:column;gap:12px;}
.faq-item{background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;}
.faq-question{width:100%;background:none;border:none;padding:20px 24px;display:flex;justify-content:space-between;align-items:center;text-align:left;font-size:15px;font-weight:600;color:var(--navy);}
.faq-question svg{width:18px;height:18px;stroke:var(--blue);fill:none;stroke-width:2;flex-shrink:0;transition:transform .2s ease;}
.faq-item.open .faq-question svg{transform:rotate(45deg);}
.faq-answer{max-height:0;overflow:hidden;transition:max-height .25s ease;}
.faq-answer p{padding:0 24px 20px;font-size:14px;color:var(--text-muted);}

/* =========================================================
   Final CTA
   ========================================================= */
.cta-band{
  background:linear-gradient(120deg,var(--blue) 0%, #1FA9C9 55%, var(--green) 130%);
  border-radius:var(--radius-lg);
  padding:72px 56px;text-align:center;color:#fff;position:relative;overflow:hidden;
  box-shadow:var(--shadow-lg);
}
.cta-band::before{content:'';position:absolute;width:380px;height:380px;border-radius:50%;
  background:radial-gradient(circle,rgba(255,255,255,0.18),transparent 70%);top:-160px;right:-90px;}
.cta-band::after{content:'';position:absolute;width:280px;height:280px;border-radius:50%;
  background:radial-gradient(circle,rgba(255,255,255,0.12),transparent 70%);bottom:-140px;left:-70px;}
.cta-band h2{font-size:clamp(26px,3.4vw,38px);font-weight:700;max-width:620px;margin:0 auto;position:relative;z-index:2;color:#fff;}
.cta-band p{margin-top:14px;font-size:15.5px;color:rgba(255,255,255,0.92);position:relative;z-index:2;}
.cta-actions{margin-top:30px;display:flex;justify-content:center;align-items:center;gap:18px;flex-wrap:wrap;position:relative;z-index:2;}
.cta-signin{margin-top:20px;font-size:14px;color:rgba(255,255,255,0.88);position:relative;z-index:2;}
.cta-signin a{font-weight:700;text-decoration:underline;color:#fff;}

/* =========================================================
   Footer
   ========================================================= */
footer.site-footer{background:var(--navy);color:#B9C4D6;padding:72px 0 28px;}
.footer-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1.2fr;gap:44px;padding-bottom:48px;border-bottom:1px solid rgba(255,255,255,0.1);}
.footer-brand .logo{color:#fff;}
.footer-brand p{margin-top:16px;font-size:13.5px;max-width:300px;color:#8A97AD;}
footer h4{font-family:'Poppins',sans-serif;color:#fff;font-size:14px;font-weight:700;margin-bottom:18px;letter-spacing:0.02em;}
footer .footer-col ul{list-style:none;}
footer .footer-col ul li{margin-bottom:11px;}
footer .footer-col a{font-size:13.5px;color:#AEB9CC;transition:color .16s ease;}
footer .footer-col a:hover{color:#fff;}
.footer-contact li{font-size:13.5px;color:#AEB9CC;margin-bottom:11px;}
.footer-bottom{display:flex;justify-content:space-between;align-items:center;padding-top:24px;flex-wrap:wrap;gap:14px;font-size:12.5px;color:#7A879C;}
.footer-bottom .legal-links{display:flex;gap:22px;}
.footer-bottom a:hover{color:#fff;}

/* =========================================================
   Responsive
   ========================================================= */
@media (max-width:1080px){
  .trust-strip{grid-template-columns:repeat(2,1fr);}
  .why-grid{grid-template-columns:repeat(2,1fr);}
  .services-grid{grid-template-columns:repeat(2,1fr);}
  .steps-grid{grid-template-columns:1fr;}
  .testimonials-grid,.health-grid{grid-template-columns:1fr;}
  .doctor-grid{grid-template-columns:1fr;gap:40px;}
  .about-photo-wrap{max-width:380px;margin:0 auto;}
  .footer-grid{grid-template-columns:1fr 1fr;}
}
@media (max-width:900px){
  .nav-links{display:none;}
  .nav-actions .btn-outline{display:none;}
  .hamburger{display:flex;}
  .hero{grid-template-columns:1fr;padding:48px 26px 120px;}
  .hero-wrap{padding-bottom:88px;}
  .hero-visual{order:-1;}
  .doctor-frame{max-width:300px;margin:0 auto;}
  .trust-strip{left:22px;right:22px;grid-template-columns:1fr 1fr;padding:20px;}
  section{padding:64px 0;}
}
@media (max-width:640px){
  .wrap{padding:0 20px;}
  .nav-inner{padding:14px 16px;}
  .logo{font-size:18px;}
  .logo-mark{width:30px;height:30px;border-radius:9px;}
  .logo-mark svg{width:16px;height:16px;}
  .logo-sub{display:none;}
  .nav-actions{gap:8px;}
  .nav-actions .btn-primary{padding:10px 16px;font-size:13px;}
  .hamburger{padding:4px;}
  .why-grid,.services-grid,.trust-strip{grid-template-columns:1fr;}
  .hero-actions .btn,.cta-actions .btn{width:100%;}
  .hero-actions,.cta-actions{flex-direction:column;}
  .footer-grid{grid-template-columns:1fr;gap:32px;}
  .footer-bottom{flex-direction:column;align-items:flex-start;}
  .cta-band{padding:44px 24px;}
  .trust-strip{bottom:-64px;padding:16px;}
  .doctor-fact{flex-direction:column;gap:4px;}
  .doctor-fact-label{min-width:0;}
}


/* Header refinement: reuse the exact logo from the signed-in dashboard. */
@media (max-width: 1180px){
  .nav-inner{gap:16px;padding-inline:22px;}
  .logo-image{width:42px;height:42px;}
  .logo-name{font-size:19px;}
  .logo-sub{max-width:190px;font-size:9px;}
  .nav-links{gap:14px;}
  .nav-links a{font-size:13px;}
  .nav-actions .btn{padding:10px 15px;font-size:13px;}
}
@media (max-width: 980px){
  .nav-links{display:none;}
  .nav-actions > .btn{display:none;}
  .nav-actions .hamburger{display:flex;}
  .nav-inner{padding:12px 20px;gap:12px;}
  .logo-sub{max-width:none;}
  .mobile-menu{padding:8px 20px 22px;}
}
@media (max-width: 480px){
  .logo-image{width:40px;height:40px;}
  .logo-name{font-size:18px;}
  .logo-sub{font-size:9px;max-width:230px;}
  .nav-inner{padding:10px 14px;}
}


/* Doctor's services and direct contact details */
.doctor-services-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;max-width:980px;margin:0 auto;}
.doctor-service-item{display:flex;align-items:flex-start;gap:13px;padding:20px 22px;background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow-sm);font-size:15px;font-weight:500;color:var(--text);}
.service-check{display:inline-flex;align-items:center;justify-content:center;flex:0 0 25px;width:25px;height:25px;border-radius:50%;background:var(--soft-cyan);color:#07866b;font-weight:800;}
.doctor-contact-section{background:var(--bg);padding:82px 0;}
.doctor-contact-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;max-width:980px;margin:0 auto;}
.doctor-contact-card{display:flex;align-items:center;gap:16px;padding:22px;background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow-sm);min-width:0;}
.doctor-contact-icon{display:flex;align-items:center;justify-content:center;flex:0 0 46px;width:46px;height:46px;border-radius:14px;background:var(--light-blue);color:var(--blue);font-size:24px;font-weight:700;}
.doctor-contact-card strong,.doctor-contact-card small{display:block;overflow-wrap:anywhere;}
.doctor-contact-card strong{font-size:15px;color:var(--navy);margin-bottom:4px;}
.doctor-contact-card small{font-size:13px;color:var(--text-muted);line-height:1.5;}
a.doctor-contact-card:hover{border-color:var(--blue);transform:translateY(-2px);transition:all .18s ease;}
.doctor-contact-cta{display:flex;align-items:center;justify-content:center;gap:20px;flex-wrap:wrap;margin-top:30px;text-align:center;}
.doctor-contact-cta p{color:var(--text-muted);font-size:15px;}
@media(max-width:650px){.doctor-services-grid,.doctor-contact-grid{grid-template-columns:1fr;}.doctor-service-item,.doctor-contact-card{padding:17px;}.doctor-contact-cta{flex-direction:column;gap:14px;}}
</style>
</head>
<body>

<!-- ================= NAV ================= -->
<header class="site-header" id="nav">
  <div class="nav-inner">
    <a href="#home" class="logo" aria-label="WEDOCARE home">
      <img class="logo-image" src="assets/wedocare-logo.png" alt="WeDoCare logo">
      <span class="logo-copy">
        <span class="logo-name">WEDOCARE</span>
        <span class="logo-sub">Online Consultation &amp; Patient Management</span>
      </span>
    </a>

    <nav class="nav-links" aria-label="Primary">
      <a href="#home">Home</a>      <a href="#schedule">Doctor Schedule</a>
      <a href="#services">Services</a>
      <a href="#doctor">About</a>
      <a href="#how">How It Works</a>
      <a href="#contact">Contact</a>
    </nav>

    <div class="nav-actions">
      <a href="login.php" class="btn btn-outline">Login</a>
      <a href="book_appointment.php" class="btn btn-primary">Book Appointment</a>
      <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  <div class="mobile-menu" id="mobileMenu">
    <a href="#home">Home</a>\n      <a href="#schedule">Doctor Schedule</a>
    <a href="#services">Services</a>
    <a href="#doctor">About</a>
    <a href="#how">How It Works</a>
    <a href="#contact">Contact</a>
    <div class="mobile-actions">
      <a href="login.php" class="btn btn-outline">Login</a>
      <a href="book_appointment.php" class="btn btn-primary">Book Appointment</a>
    </div>
  </div>
</header>

<!-- ================= HERO ================= -->
<div class="wrap hero-wrap" id="home">
  <div class="hero">
    <div class="hero-content">
      <span class="eyebrow"><span class="dot"></span>Your Health, Our Priority</span>
      <h1>Quality Healthcare, Right at Your Fingertips</h1>
      <p class="lead">Connect with <?= htmlspecialchars($docName) ?> through convenient online consultation and personalized healthcare services.</p>
      <div class="hero-actions">
        <a href="book_appointment.php" class="btn btn-primary">Book an Appointment</a>
        <a href="#why" class="btn btn-outline">Learn More</a>
      </div>
    </div>

    <div class="hero-visual">
      <div class="doctor-frame">
        <img src="<?= htmlspecialchars($heroImg) ?>" alt="<?= htmlspecialchars($docName) ?>"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="ph-placeholder" style="display:none;">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
          <p>Doctor photo will appear here once uploaded under Settings.</p>
        </div>
      </div>
    </div>

    <!-- Real system features (no fabricated statistics) -->
    <div class="trust-strip">
      <div class="trust-item">
        <span class="ico"><svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 18v3"/><path d="M8 11h2l1.5-3 2 5 1.5-3H16"/></svg></span>
        <div><h4>Online Consultation</h4><span>Secure video visits</span></div>
      </div>
      <div class="trust-item">
        <span class="ico"><svg viewBox="0 0 24 24"><path d="M12 21C12 21 4 15.5 4 9.8C4 6.6 6.5 4 9.5 4C10.9 4 12 4.7 12 4.7C12 4.7 13.1 4 14.5 4C17.5 4 20 6.6 20 9.8C20 15.5 12 21 12 21Z"/></svg></span>
        <div><h4>Personalized Care</h4><span>Care built around you</span></div>
      </div>
      <div class="trust-item">
        <span class="ico"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/><path d="M9 13h6M9 17h4"/></svg></span>
        <div><h4>E-Prescription</h4><span>Digital prescriptions</span></div>
      </div>
      <div class="trust-item">
        <span class="ico"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
        <div><h4>Secure Records</h4><span>Protected patient data</span></div>
      </div>
    </div>
  </div>
</div>

<!-- ================= DOCTOR SCHEDULE ================= -->
<div class="wrap">
  <section id="schedule" class="schedule-section">
    <div class="section-head">
      <span class="eyebrow"><span class="dot"></span>Plan Your Visit</span>
      <h2>Dr. <?= htmlspecialchars($docFirst) ?>’s Consultation Schedule</h2>
      <p>Check the clinic’s published consultation hours before requesting an appointment. Schedule updates are managed by the doctor.</p>
    </div>
    <div class="schedule-panel">
      <div class="schedule-intro">
        <span class="eyebrow"><span class="dot"></span>Clinic availability</span>
        <h3>Care that fits your schedule.</h3>
        <p>Choose an available consultation period, then submit an appointment request. Your booking is not confirmed until the clinic accepts it.</p>
        <a href="book_appointment.php" class="btn btn-white">Book an Appointment</a>
      </div>
      <div class="schedule-card">
        <div class="schedule-card-head">
          <h3>Weekly Schedule</h3>
          <?php if ($hasPublishedSchedule): ?><span class="schedule-status">Published hours</span>
          <?php else: ?><span class="schedule-status" style="background:#FFF4D9;color:#855B00;">Awaiting hours</span><?php endif; ?>
        </div>
        <div class="schedule-list">
          <?php foreach ($weekdayNames as $dayNo => $dayName): $dayRow = isset($scheduleByDay[$dayNo]) ? $scheduleByDay[$dayNo] : null; $dayOpen = $dayRow && !empty($dayRow['is_available']) && !empty($dayRow['start_time']) && !empty($dayRow['end_time']); ?>
            <div class="schedule-row">
              <div class="schedule-day"><?= htmlspecialchars($dayName) ?></div>
              <div class="schedule-time">
                <?php if ($dayOpen): ?>
                  <strong><?= htmlspecialchars(date('g:i A', strtotime($dayRow['start_time']))) ?> – <?= htmlspecialchars(date('g:i A', strtotime($dayRow['end_time']))) ?></strong>
                  <span class="schedule-mode"><?= htmlspecialchars($dayRow['appointment_mode']) ?> consultation<?= !empty($dayRow['notes']) ? ' · ' . htmlspecialchars($dayRow['notes']) : '' ?></span>
                <?php else: ?><span class="schedule-closed"><?= $dayRow ? 'Not available' : 'Not published' ?></span><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="schedule-note">
          <?php if ($hasPublishedSchedule): ?>
            Published hours are general clinic hours. Specific appointment slots may fill up; submit a request to confirm availability.
          <?php else: ?>
            Dr. <?= htmlspecialchars($docFirst) ?>’s weekly hours have not been published yet. The doctor can add or update the schedule from Settings.
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- ================= HOW IT WORKS ================= -->
<div class="wrap">
  <section id="how" style="padding-top:160px;">
    <div class="section-head center">
      <span class="eyebrow"><span class="dot"></span>How It Works</span>
      <h2>Getting Started Is Simple</h2>
      <p>Three easy steps to consult with <?= htmlspecialchars($docName) ?> through WEDOCARE.</p>
    </div>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="step-num">Step 01</div>
        <h3>Book an Appointment</h3>
        <p>Select your preferred appointment schedule.</p>
      </div>
      <div class="step-card">
        <div class="step-icon"><svg viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></div>
        <div class="step-num">Step 02</div>
        <h3>Consult with the Doctor</h3>
        <p>Attend your scheduled online consultation.</p>
      </div>
      <div class="step-card">
        <div class="step-icon"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg></div>
        <div class="step-num">Step 03</div>
        <h3>Receive Your Medical Documents</h3>
        <p>Access your consultation information and digital medical documents.</p>
      </div>
    </div>
  </section>
</div>

<!-- ================= WHY CHOOSE WEDOCARE ================= -->
<section class="why-section" id="why">
  <div class="wrap">
    <div class="section-head center">
      <span class="eyebrow"><span class="dot"></span>Why WEDOCARE</span>
      <h2>Why Choose WEDOCARE?</h2>
      <p>WEDOCARE makes healthcare more convenient by bringing online consultation, appointment scheduling, patient management, and digital medical services into one platform.</p>
    </div>
    <div class="why-grid">
      <div class="why-card">
        <div class="why-icon"><svg viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></div>
        <h3>Convenient Online Consultation</h3>
        <p>Consult with your doctor from the comfort of your home, without the need to travel.</p>
      </div>
      <div class="why-card">
        <div class="why-icon"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></div>
        <h3>Secure Patient Records</h3>
        <p>Your medical information is kept organized and accessible only through your patient account.</p>
      </div>
      <div class="why-card">
        <div class="why-icon"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/><path d="M9 13h6M9 17h4"/></svg></div>
        <h3>Digital E-Prescription</h3>
        <p>Receive and manage your prescriptions digitally through the platform.</p>
      </div>
      <div class="why-card">
        <div class="why-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <h3>Easy Appointment Booking</h3>
        <p>Request and track appointments in just a few clicks — online or walk-in.</p>
      </div>
    </div>
  </div>
</section>

<!-- ================= DOCTOR'S SERVICES ================= -->
<div class="wrap">
  <section id="services">
    <div class="section-head center">
      <span class="eyebrow"><span class="dot"></span>Services Offered</span>
      <h2>Dr. <?= htmlspecialchars($docFirst) ?>’s Services</h2>
      <p>Medical services offered by Dr. Kendrick Kerk Deguito, MD.</p>
    </div>
    <div class="doctor-services-grid">
      <div class="doctor-service-item"><span class="service-check">✓</span><span>Adult &amp; Pediatric Non-Urgent Cases</span></div>
      <div class="doctor-service-item"><span class="service-check">✓</span><span>Fit to Work Certificate (For Assessment)</span></div>
      <div class="doctor-service-item"><span class="service-check">✓</span><span>Fit to Travel Certificate</span></div>
      <div class="doctor-service-item"><span class="service-check">✓</span><span>Medical Certificate</span></div>
      <div class="doctor-service-item"><span class="service-check">✓</span><span>Prescription Refill</span></div>
      <div class="doctor-service-item"><span class="service-check">✓</span><span>Laboratory Requests</span></div>
      <div class="doctor-service-item"><span class="service-check">✓</span><span>Diagnostic Imaging Requests</span></div>
      <div class="doctor-service-item"><span class="service-check">✓</span><span>Routine Check-up</span></div>
    </div>
  </section>
</div>

<!-- ================= DOCTOR CONTACT INFORMATION ================= -->
<section class="doctor-contact-section" id="doctor-contact">
  <div class="wrap">
    <div class="section-head center">
      <span class="eyebrow"><span class="dot"></span>Get in Touch</span>
      <h2>Contact Dr. Deguito</h2>
      <p>For appointments and inquiries, use the contact details below.</p>
    </div>
    <div class="doctor-contact-grid">
      <a class="doctor-contact-card" href="tel:+639501397568">
        <span class="doctor-contact-icon">☎</span>
        <span><strong>Phone</strong><small>+639 501 397 568</small></span>
      </a>
      <a class="doctor-contact-card" href="mailto:kendeguitomd@gmail.com">
        <span class="doctor-contact-icon">✉</span>
        <span><strong>Email</strong><small>kendeguitomd@gmail.com</small></span>
      </a>
      <div class="doctor-contact-card">
        <span class="doctor-contact-icon">◷</span>
        <span><strong>Consultation Hours</strong><small>Monday – Friday, 8:00 AM – 5:00 PM</small></span>
      </div>
      <a class="doctor-contact-card" href="https://thefilipinodoctor.com/doctor/kendrick-kerk-deguito" target="_blank" rel="noopener noreferrer">
        <span class="doctor-contact-icon">↗</span>
        <span><strong>Online Doctor Profile</strong><small>View Dr. Deguito on The Filipino Doctor</small></span>
      </a>
    </div>
    <div class="doctor-contact-cta">
      <p>Ready to consult? Request an appointment through WEDOCARE.</p>
      <a href="book_appointment.php" class="btn btn-primary">Book an Appointment</a>
    </div>
  </div>
</section>

<!-- ================= PAYMENT METHODS (existing feature) ================= -->
<section class="pay-section" id="payments">
  <div class="wrap">
    <div class="section-head center">
      <span class="eyebrow"><span class="dot"></span>Convenient and Secure</span>
      <h2>Accepted Payment Methods</h2>
      <p>Pay your consultation fee online — scan, pay, and upload your receipt.</p>
    </div>
    <div class="pay-row">
      <div class="pay-item">
        <div class="pay-badge">
          <img src="assets/gcash.png" alt="GCash" style="width:44px;height:44px;object-fit:contain;"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;font-size:10px;color:var(--text-muted);text-align:center;">GCash<br>logo here</div>
        </div>
        <div class="label" style="color:#0074E4;">GCash</div>
      </div>
      <div class="pay-item">
        <div class="pay-badge">
          <img src="assets/maya.png" alt="Maya" style="width:44px;height:44px;object-fit:contain;"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;font-size:10px;color:var(--text-muted);text-align:center;">Maya<br>logo here</div>
        </div>
        <div class="label" style="color:#00A85A;">Maya</div>
      </div>
      <div class="pay-item">
        <div class="pay-badge">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.8">
            <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 6v12M18 6v12" stroke-opacity="0.5"/>
          </svg>
        </div>
        <div class="label" style="color:var(--blue);">Cash</div>
      </div>
    </div>
    <div class="pay-note">Scan our GCash or Maya QR code directly from your patient portal when paying for a consultation.</div>
  </div>
</section>

<!-- ================= MEET THE DOCTOR ================= -->
<section class="doctor-section" id="doctor">
  <div class="wrap" style="padding:80px 32px;">
    <div class="doctor-grid">
      <div class="about-photo-wrap">
        <img src="<?= htmlspecialchars($aboutImg) ?>" alt="<?= htmlspecialchars($docName) ?>"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="ph-placeholder" style="display:none;">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
          <p>Doctor photo will appear here once uploaded under Settings.</p>
        </div>
      </div>
      <div class="doctor-info">
        <span class="eyebrow"><span class="dot"></span>Meet Your Doctor</span>
        <h2><?= htmlspecialchars($docName) ?></h2>
        <div class="role"><?= htmlspecialchars($docSpec) ?></div>
        <p class="doctor-bio"><?= nl2br(htmlspecialchars($docBio)) ?></p>
        <div class="doctor-facts">
          <?php if ($docLic): ?>
          <div class="doctor-fact"><div class="doctor-fact-label">PRC License</div><div class="doctor-fact-value"><?= htmlspecialchars($docLic) ?></div></div>
          <?php endif; ?>
          <div class="doctor-fact"><div class="doctor-fact-label">Clinic Address</div><div class="doctor-fact-value"><?= htmlspecialchars($docAddr) ?></div></div>
          <?php if ($docPhone): ?>
          <div class="doctor-fact"><div class="doctor-fact-label">Contact Number</div><div class="doctor-fact-value"><?= htmlspecialchars($docPhone) ?></div></div>
          <?php endif; ?>
          <div class="doctor-fact"><div class="doctor-fact-label">Consultation Type</div><div class="doctor-fact-value">Walk-in and online</div></div>
        </div>
        <a href="book_appointment.php" class="btn btn-primary">Book an Appointment</a>
      </div>
    </div>
  </div>
</section>

<!-- ================= PATIENT EXPERIENCE ================= -->
<div class="wrap">
  <section id="testimonials">
    <div class="section-head center">
      <span class="eyebrow"><span class="dot"></span>Patient Experience</span>
      <h2>Healthcare Designed Around You</h2>
      <p>WEDOCARE focuses on convenience, accessibility, and organized patient care so you can focus on your health.</p>
    </div>

    <?php if (empty($testimonials)): ?>
      <div class="testimonial-empty">
        <div class="icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
        <h3>Patient reviews coming soon</h3>
        <p>Once patients begin sharing their experience with WEDOCARE, their reviews will appear here.</p>
      </div>
    <?php else: ?>
      <div class="testimonials-grid">
        <?php foreach ($testimonials as $t): ?>
          <div class="testimonial-card">
            <div class="stars">★★★★★</div>
            <p class="body-text"><?= htmlspecialchars($t['body']) ?></p>
            <div class="testimonial-footer">
              <div class="avatar-ph"><?= htmlspecialchars(strtoupper(substr($t['name'],0,1))) ?></div>
              <span><?= htmlspecialchars($t['name']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<!-- ================= HEALTH INFORMATION ================= -->
<section class="why-section" id="health">
  <div class="wrap">
    <div class="section-head center">
      <span class="eyebrow"><span class="dot"></span>Stay Informed</span>
      <h2>Health Information</h2>
      <p>Resources and updates from WEDOCARE — more content is on its way.</p>
    </div>
    <div class="health-grid">
      <div class="health-card">
        <span class="health-badge">Coming soon</span>
        <div class="health-icon"><svg viewBox="0 0 24 24"><path d="M12 21C12 21 4 15.5 4 9.8C4 6.6 6.5 4 9.5 4C10.9 4 12 4.7 12 4.7C12 4.7 13.1 4 14.5 4C17.5 4 20 6.6 20 9.8C20 15.5 12 21 12 21Z"/></svg></div>
        <h3>Health Tips</h3>
        <p>General wellness guidance from <?= htmlspecialchars($docName) ?> will be published here.</p>
      </div>
      <div class="health-card">
        <span class="health-badge">Coming soon</span>
        <div class="health-icon"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg></div>
        <h3>Medical Information</h3>
        <p>Clinic announcements and general medical information will be posted in this space.</p>
      </div>
      <div class="health-card">
        <span class="health-badge">Coming soon</span>
        <div class="health-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <h3>Patient Resources</h3>
        <p>Guides on using your patient portal and preparing for your visit will live here.</p>
      </div>
    </div>
  </div>
</section>

<!-- ================= FAQ ================= -->
<section class="faq-section" id="faq">
  <div class="wrap">
    <div class="section-head center">
      <span class="eyebrow"><span class="dot"></span>Frequently Asked</span>
      <h2>Questions About WEDOCARE</h2>
      <p>A quick look at how the platform works.</p>
    </div>
    <div class="faq-list" id="faqList">
      <div class="faq-item">
        <button class="faq-question" type="button">
          How do I book an appointment?
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <div class="faq-answer"><p>Create a free account, then choose an available date and time to request your appointment — online or walk-in.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question" type="button">
          Is online consultation available?
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <div class="faq-answer"><p>Yes. Once your appointment is confirmed, you can consult with <?= htmlspecialchars($docName) ?> remotely through a private video call.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question" type="button">
          What payment methods are accepted?
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <div class="faq-answer"><p>WEDOCARE accepts GCash, Maya, and cash payments for consultations.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question" type="button">
          Is my medical information kept private?
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <div class="faq-answer"><p>Your records and consultation history are kept within your patient account and are handled with care and access controls.</p></div>
      </div>
    </div>
  </div>
</section>

<!-- ================= FINAL CTA ================= -->
<div class="wrap">
  <div class="cta-band">
    <h2>Ready to Take the Next Step in Your Healthcare?</h2>
    <p>Book an online consultation with <?= htmlspecialchars($docName) ?> and experience convenient digital healthcare with WEDOCARE.</p>
    <div class="cta-actions">
      <a href="book_appointment.php" class="btn btn-white">Book an Appointment</a>
      <a href="login.php" class="btn btn-outline-light">Login</a>
    </div>
    <div class="cta-signin">Already registered? <a href="login.php">Sign in</a></div>
  </div>
</div>

<!-- ================= FOOTER ================= -->
<footer class="site-footer" id="contact">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="#home" class="logo">
          <span class="logo-mark">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 3L4 6.5V11.5C4 16.2 7.4 20.4 12 21.5C16.6 20.4 20 16.2 20 11.5V6.5L12 3Z" stroke="white" stroke-width="1.6" stroke-linejoin="round"/>
              <path d="M12 8V15M8.5 11.5H15.5" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </span>
          WEDO<span style="color:#7FCBFF;">CARE</span>
        </a>
        <p>Online Consultation &amp; Patient Management. Personalized and accessible healthcare through a modern digital clinic experience.</p>
      </div>

      <div class="footer-col">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#home">Home</a>\n      <a href="#schedule">Doctor Schedule</a></li>
          <li><a href="#doctor">About</a></li>
          <li><a href="#services">Services</a></li>
          <li><a href="#how">How It Works</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Services</h4>
        <ul>
          <li><a href="#services">Online Consultation</a></li>
          <li><a href="book_appointment.php">Appointment Booking</a></li>
          <li><a href="#services">Patient Management</a></li>
          <li><a href="#services">E-Prescription</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Contact</h4>
        <ul class="footer-contact">
          <?php if ($docPhone): ?>
            <li><?= htmlspecialchars($docPhone) ?></li>
          <?php endif; ?>
          <li><?= htmlspecialchars($docAddr) ?></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> WEDOCARE. All rights reserved.</span>
      <div class="legal-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>

<script>
  // Sticky nav shadow on scroll (existing behavior, preserved)
  window.addEventListener('scroll', () => {
    document.getElementById('nav').classList.toggle('scrolled', window.scrollY > 8);
  });

  // Mobile hamburger menu
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      const isOpen = mobileMenu.classList.toggle('open');
      hamburger.classList.toggle('active');
      hamburger.setAttribute('aria-expanded', isOpen);
    });
    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileMenu.classList.remove('open');
        hamburger.classList.remove('active');
        hamburger.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // FAQ accordion
  document.querySelectorAll('.faq-item').forEach(item => {
    const question = item.querySelector('.faq-question');
    const answer = item.querySelector('.faq-answer');
    question.addEventListener('click', () => {
      const isOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item.open').forEach(openItem => {
        if (openItem !== item) {
          openItem.classList.remove('open');
          openItem.querySelector('.faq-answer').style.maxHeight = null;
        }
      });
      item.classList.toggle('open', !isOpen);
      answer.style.maxHeight = !isOpen ? answer.scrollHeight + 'px' : null;
    });
  });
</script>

</body>
</html>