// Common JavaScript functions for the Online Examination System

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Confirm delete actions
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

// Exam timer functionality
function initExamTimer(durationInSeconds) {
    if (!document.getElementById('countdown')) return;
    
    let remainingTime = durationInSeconds;
    const countdownElement = document.getElementById('countdown');
    
    const timerInterval = setInterval(function() {
        const minutes = Math.floor(remainingTime / 60);
        const seconds = remainingTime % 60;
        
        countdownElement.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
        
        if (remainingTime <= 300) { // 5 minutes remaining
            countdownElement.classList.add('text-danger');
            countdownElement.classList.add('fw-bold');
        }
        
        if (remainingTime <= 0) {
            clearInterval(timerInterval);
            alert('Time is up! Your exam will be submitted automatically.');
            document.getElementById('examForm').submit();
        }
        
        remainingTime--;
    }, 1000);
    
    // Store timer in sessionStorage to handle page refreshes
    sessionStorage.setItem('examTimerStart', Date.now());
    sessionStorage.setItem('examTimerDuration', durationInSeconds);
    
    // Handle page refresh or navigation
    window.addEventListener('beforeunload', function(e) {
        if (document.getElementById('examForm')) {
            e.preventDefault();
            e.returnValue = 'You are in the middle of an exam. Are you sure you want to leave?';
        }
    });
}

// Question form validation
function validateQuestionForm() {
    const questionType = document.getElementById('question_type').value;
    const questionText = document.getElementById('question_text').value;
    const marks = document.getElementById('marks').value;
    
    if (!questionText.trim()) {
        alert('Please enter the question text');
        return false;
    }
    
    if (marks <= 0) {
        alert('Marks must be greater than zero');
        return false;
    }
    
    if (questionType === 'multiple_choice') {
        const optionA = document.getElementById('option_a').value;
        const optionB = document.getElementById('option_b').value;
        const optionC = document.getElementById('option_c').value;
        const optionD = document.getElementById('option_d').value;
        
        if (!optionA.trim() || !optionB.trim() || !optionC.trim() || !optionD.trim()) {
            alert('Please fill in all options for multiple choice questions');
            return false;
        }
    }
    
    return true;
}
