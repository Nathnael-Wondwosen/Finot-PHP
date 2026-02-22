// Ethiopian Calendar for Birth Date Filters - Exact implementation from working edit drawer
function initializeFilterEthiopianCalendar() {
    console.log('Starting Ethiopian calendar filter initialization...');
    
    const fromYearSel = document.getElementById('birth_from_year');
    const fromMonthSel = document.getElementById('birth_from_month');
    const fromDaySel = document.getElementById('birth_from_day');
    const fromHidden = document.getElementById('date_from_hidden');
    
    const toYearSel = document.getElementById('birth_to_year');
    const toMonthSel = document.getElementById('birth_to_month');
    const toDaySel = document.getElementById('birth_to_day');
    const toHidden = document.getElementById('date_to_hidden');
    
    if (!fromYearSel || !fromMonthSel || !fromDaySel || !toYearSel || !toMonthSel || !toDaySel) {
        console.log('Ethiopian calendar filter elements not found, skipping initialization');
        return;
    }
    
    console.log('All filter elements found, proceeding with initialization');
    
    const monthNames = ['መስከረም', 'ጥቅምት', 'ሕዳር', 'ታህሳስ', 'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ጳጉሜ'];
    
    function getCurrentEthiopianYear() {
        const today = new Date();
        const gYear = today.getFullYear();
        const gMonth = today.getMonth() + 1;
        const gDay = today.getDate();
        let eYear = gYear - 8;
        if (gMonth > 9 || (gMonth === 9 && gDay >= 11)) eYear = gYear - 7;
        return eYear;
    }
    
    function isEthiopianLeapYear(eYear) {
        return eYear % 4 === 3;
    }
    
    function daysInEthiopianMonth(eYear, eMonth) {
        if (eMonth >= 1 && eMonth <= 12) return 30;
        return isEthiopianLeapYear(eYear) ? 6 : 5; // Pagume
    }
    
    function populateFromYears() {
        const current = getCurrentEthiopianYear();
        const min = current - 40;
        fromYearSel.innerHTML = '<option value="">ዓመት</option>';
        for (let y = current; y >= min; y--) {
            const opt = document.createElement('option');
            opt.value = String(y);
            opt.textContent = y;
            fromYearSel.appendChild(opt);
        }
        console.log('Populated FROM years, count:', fromYearSel.options.length);
    }
    
    function populateToYears() {
        const current = getCurrentEthiopianYear();
        const min = current - 40;
        toYearSel.innerHTML = '<option value="">ዓመት</option>';
        for (let y = current; y >= min; y--) {
            const opt = document.createElement('option');
            opt.value = String(y);
            opt.textContent = y;
            toYearSel.appendChild(opt);
        }
        console.log('Populated TO years, count:', toYearSel.options.length);
    }
    
    function populateFromMonths() {
        fromMonthSel.innerHTML = '<option value="">ወር</option>';
        monthNames.forEach((name, idx) => {
            const opt = document.createElement('option');
            opt.value = String(idx + 1);
            opt.textContent = name;
            fromMonthSel.appendChild(opt);
        });
        console.log('Populated FROM months, count:', fromMonthSel.options.length);
    }
    
    function populateToMonths() {
        toMonthSel.innerHTML = '<option value="">ወር</option>';
        monthNames.forEach((name, idx) => {
            const opt = document.createElement('option');
            opt.value = String(idx + 1);
            opt.textContent = name;
            toMonthSel.appendChild(opt);
        });
        console.log('Populated TO months, count:', toMonthSel.options.length);
    }
    
    function populateFromDays() {
        const y = parseInt(fromYearSel.value, 10);
        const m = parseInt(fromMonthSel.value, 10);
        fromDaySel.innerHTML = '<option value="">ቀን</option>';
        if (!y || !m) return;
        const dim = daysInEthiopianMonth(y, m);
        for (let d = 1; d <= dim; d++) {
            const opt = document.createElement('option');
            opt.value = String(d);
            opt.textContent = String(d);
            fromDaySel.appendChild(opt);
        }
        updateFromHiddenField();
    }
    
    function populateToDays() {
        const y = parseInt(toYearSel.value, 10);
        const m = parseInt(toMonthSel.value, 10);
        toDaySel.innerHTML = '<option value="">ቀን</option>';
        if (!y || !m) return;
        const dim = daysInEthiopianMonth(y, m);
        for (let d = 1; d <= dim; d++) {
            const opt = document.createElement('option');
            opt.value = String(d);
            opt.textContent = String(d);
            toDaySel.appendChild(opt);
        }
        updateToHiddenField();
    }
    
    function updateFromHiddenField() {
        const y = fromYearSel.value;
        const m = fromMonthSel.value;
        const d = fromDaySel.value;
        
        if (y && m && d && fromHidden) {
            const formattedDate = y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            fromHidden.value = formattedDate;
        } else if (fromHidden) {
            fromHidden.value = '';
        }
    }
    
    function updateToHiddenField() {
        const y = toYearSel.value;
        const m = toMonthSel.value;
        const d = toDaySel.value;
        
        if (y && m && d && toHidden) {
            const formattedDate = y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            toHidden.value = formattedDate;
        } else if (toHidden) {
            toHidden.value = '';
        }
    }
    
    // Event listeners
    fromYearSel.addEventListener('change', function() { 
        populateFromDays(); 
        updateFromHiddenField();
    });
    fromMonthSel.addEventListener('change', function() { 
        populateFromDays(); 
        updateFromHiddenField();
    });
    fromDaySel.addEventListener('change', function() {
        updateFromHiddenField();
    });
    
    toYearSel.addEventListener('change', function() { 
        populateToDays(); 
        updateToHiddenField();
    });
    toMonthSel.addEventListener('change', function() { 
        populateToDays(); 
        updateToHiddenField();
    });
    toDaySel.addEventListener('change', function() {
        updateToHiddenField();
    });
    
    // Initialize
    populateFromYears();
    populateFromMonths();
    populateToYears();
    populateToMonths();
    
    // Set existing values from URL parameters if present
    const urlParams = new URLSearchParams(window.location.search);
    const dateFrom = urlParams.get('date_from');
    const dateTo = urlParams.get('date_to');
    
    if (dateFrom) {
        const parts = dateFrom.split('-');
        if (parts.length === 3) {
            fromYearSel.value = parts[0];
            fromMonthSel.value = parseInt(parts[1], 10).toString();
            populateFromDays();
            fromDaySel.value = parseInt(parts[2], 10).toString();
            updateFromHiddenField();
        }
    }
    
    if (dateTo) {
        const parts = dateTo.split('-');
        if (parts.length === 3) {
            toYearSel.value = parts[0];
            toMonthSel.value = parseInt(parts[1], 10).toString();
            populateToDays();
            toDaySel.value = parseInt(parts[2], 10).toString();
            updateToHiddenField();
        }
    }
    
    console.log('Ethiopian calendar filter initialization complete!');
}

// Multiple initialization attempts to handle different loading scenarios
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, attempting Ethiopian calendar initialization...');
    
    // Immediate attempt
    initializeFilterEthiopianCalendar();
    
    // Retry after short delay for Alpine.js
    setTimeout(function() {
        console.log('Retry 1: Ethiopian calendar initialization...');
        initializeFilterEthiopianCalendar();
    }, 250);
    
    // Final retry with longer delay
    setTimeout(function() {
        console.log('Retry 2: Ethiopian calendar initialization...');
        initializeFilterEthiopianCalendar();
    }, 1000);
});

// Also try on window load as fallback
window.addEventListener('load', function() {
    console.log('Window loaded, final Ethiopian calendar initialization attempt...');
    setTimeout(function() {
        initializeFilterEthiopianCalendar();
    }, 100);
});