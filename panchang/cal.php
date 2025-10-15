<!DOCTYPE html>
<html>
<head>
    <title>Spiritual Panchang – 2025 Calendar</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #fdf6e3; padding: 20px; }
        h2 { text-align: center; color: #6b4226; }
        select { margin: 10px auto; display: block; font-size: 14px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th { background: #eee; padding: 5px; }
        td {
            border: 1px solid #ccc;
            padding: 10px;
            vertical-align: top;
            width: 14.28%;
            height: 160px;
            cursor: pointer;
            position: relative;
            background: #fff;
        }
        .shubh { background-color: #e6ffe6; border-color: #8bc34a; }
        .retry-timer {
            position: absolute;
            bottom: 5px;
            right: 5px;
            font-size: 11px;
            color: #900;
        }
        .retry-btn {
            position: absolute;
            bottom: 5px;
            left: 5px;
            font-size: 11px;
            background: #fff0f0;
            border: 1px solid #900;
            color: #900;
            cursor: pointer;
        }
        .festival-icon {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 10%;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border: 2px solid #6b4226;
            padding: 20px;
            width: 400px;
            max-height: 80%;
            overflow-y: auto;
            box-shadow: 0 0 15px rgba(0,0,0,0.4);
            z-index: 1000;
        }
        .modal-close {
            position: absolute;
            top: 5px;
            right: 10px;
            cursor: pointer;
            font-weight: bold;
            color: #900;
        }
    </style>
</head>
<body>
    <h2>🌸 Spiritual Panchang Calendar – 2025 🌸</h2>
    <select id="month-select">
        <?php
        $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        foreach ($months as $i => $name) {
            echo "<option value='".($i+1)."'>$name</option>";
        }
        ?>
    </select>
    <select id="region-select">
        <option value="default">All Regions</option>
        <option value="hindi">Hindi</option>
        <option value="tamil">Tamil</option>
        <option value="bengali">Bengali</option>
        <option value="gujarati">Gujarati</option>
    </select>
    <div id="calendar"></div>
    <div id="modal" class="modal">
        <div class="modal-close" onclick="closeModal()">✖</div>
        <div id="modal-content">Loading...</div>
    </div>

<script>
let retryCooldown = 60;
let pendingDays = [];
let timers = {};
let region = 'default';
let currentMonth = new Date().getMonth() + 1;

function renderCalendar(month) {
    currentMonth = month;
    const daysInMonth = new Date(2025, month, 0).getDate();
    const firstDay = new Date(2025, month - 1, 1).getDay();
    let html = `<table><tr>`;
    const weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    weekdays.forEach(d => html += `<th>${d}</th>`);
    html += `</tr><tr>`;
    for (let i = 0; i < firstDay; i++) html += `<td></td>`;
    let dayOfWeek = firstDay;
    for (let d = 1; d <= daysInMonth; d++) {
        html += `<td id="day-${month}-${d}"><strong>${d}</strong><br>Loading...</td>`;
        dayOfWeek++;
        if (dayOfWeek === 7) {
            html += `</tr><tr>`;
            dayOfWeek = 0;
        }
    }
    html += `</tr></table>`;
    document.getElementById('calendar').innerHTML = html;
    for (let d = 1; d <= daysInMonth; d++) fetchDay(month, d);
}

function fetchDay(month, day) {
    const cell = document.getElementById(`day-${month}-${day}`);
    fetch(`cache/2025-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}.json`)
        .then(res => res.json())
        .then(data => {
            if (!data || !data.vaara || !data.tithi || !data.karana) {
                markPending(cell, month, day);
                return;
            }
            if (region !== 'default' && data.region && data.region !== region) {
                cell.innerHTML = `<strong>${day}</strong><br><em>Filtered</em>`;
                cell.className = '';
                return;
            }
            cell.className = data.shubh ? 'shubh' : '';
            cell.innerHTML = `<strong>${day}</strong><br>
                🗓️ ${data.vaara}<br>
                🌙 ${data.tithi}<br>
                🌟 ${data.nakshatra}<br>
                🔆 ${data.sunrise} - ${data.sunset}`;
            if (data.festivalIcon) {
                const icon = document.createElement('img');
                icon.src = data.festivalIcon;
                icon.className = 'festival-icon';
                cell.appendChild(icon);
            }
            cell.onclick = () => showModal(month, day);
            delete timers[`${month}-${day}`];
        })
        .catch(() => {
            markPending(cell, month, day);
        });
}

function markPending(cell, month, day) {
    cell.innerHTML = `<strong>${day}</strong><br><div style="color:red">Partial/N/A</div>`;
    cell.className = '';
    const key = `${month}-${day}`;
    if (!pendingDays.includes(key)) pendingDays.push(key);
    if (!timers[key]) {
        const timer = document.createElement('div');
        timer.className = 'retry-timer';
        timer.id = 'timer-' + key;
        timer.textContent = `Retry in ${retryCooldown}s`;
        cell.appendChild(timer);
        timers[key] = timer;
    }
    const retryBtn = document.createElement('button');
    retryBtn.className = 'retry-btn';
    retryBtn.textContent = '🔁 Retry';
    retryBtn.onclick = (e) => {
        e.stopPropagation();
        if (!pendingDays.includes(key)) pendingDays.push(key);
        timers[key].textContent = `Queued…`;
    };
    cell.appendChild(retryBtn);
}

function updateTimers() {
    retryCooldown--;
    Object.keys(timers).forEach(key => {
        const timer = timers[key];
        if (timer) timer.textContent = `Retry in ${retryCooldown}s`;
    });
    if (retryCooldown <= 0) {
        batchFetch();
        retryCooldown = 60;
    }
}

function batchFetch() {
    if (pendingDays.length === 0) return;
    const batch = pendingDays.splice(0, 5);
    fetch('batch.php?days=' + batch.join(','))
        .then(() => {
            batch.forEach(key => {
                const [month, day] = key.split('-');
                setTimeout(() => fetchDay(month, day), 3000);
            });
        });
}

function showModal(month, day) {
    fetch(`cache/2025-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}.json`)
        .then(res => res.json())
        .then(data => {
            const html = `
                <h3>🕉️ Panchang for ${day} ${monthName(month)} 2025</h3>
                <p><strong>Vaara:</strong> ${data.vaara}</p>
                <p><strong>Tithi:</strong> ${data.tithi}</p>
                <p><strong>Nakshatra:</strong> ${data.nakshatra}</p>
                <p><strong>Yoga:</strong> ${data.yoga}</p>
                <p><strong>Karana:</strong> ${data.karana}</p>
                <p><strong>Sunrise:</strong> ${data.sunrise}</p>
                <p><strong>Sunset:</strong> ${data.sunset}</p>
                <p><strong>Rahu Kalam:</strong> ${data.rahuKalam}</p>
                <p><strong>Abhijit Muhurat:</strong> ${data.abhijit}</p>
                <p><strong>Shubh Day:</strong> ${data.shubh ? '✅ Yes' : '❌ No'}</p>
                ${data.festivalIcon ? `<img src="${data.festivalIcon}" width="40">` : ''}
                <hr>
                <pre>${JSON.stringify(data.raw, null, 2)}</pre>
            `;
            document.getElementById('modal-content').innerHTML = html;
            document.getElementById('modal').style.display = 'block';
        });
}

function monthName(m) {
    return new Date(2025, m - 1).toLocaleString('default', { month: 'long' });
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

function applyRegionFilter() {
    region = document.getElementById('region-select').value;
    renderCalendar(currentMonth);
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('month-select').value = currentMonth;
    renderCalendar(currentMonth);
    setInterval(updateTimers, 1000);
    document.getElementById('month-select').onchange = (e) => {
        renderCalendar(parseInt(e.target.value));
    };
    document.getElementById('region-select').onchange = applyRegionFilter;
});
</script>
</body>
</html>
