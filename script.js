// CGPA Calculator JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Initialize event listeners
    document.getElementById('addCourseBtn').addEventListener('click', addCourse);
    document.getElementById('predictBtn').addEventListener('click', predictTargetGPA);
    document.getElementById('cgpaForm').addEventListener('submit', handleFormSubmit);
    
    // Initialize modal close functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal();
        }
    });
    
    // Initialize escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    
    // Update remove button states
    updateRemoveButtons();
}

function addCourse() {
    const tableBody = document.getElementById('courseTableBody');
    const newRow = createCourseRow();
    
    // Add animation class
    newRow.classList.add('adding');
    tableBody.appendChild(newRow);
    
    // Remove animation class after animation completes
    setTimeout(() => {
        newRow.classList.remove('adding');
    }, 300);
    
    updateRemoveButtons();
    
    // Focus on the first input of the new row
    const firstInput = newRow.querySelector('input[name="course_name[]"]');
    if (firstInput) {
        firstInput.focus();
    }
}

function createCourseRow() {
    const row = document.createElement('tr');
    row.className = 'course-row';
    row.innerHTML = `
        <td>
            <input type="text" name="course_name[]" placeholder="e.g., Mathematics 101" class="form-input" required>
        </td>
        <td>
            <input type="number" name="credit_hours[]" min="1" max="6" placeholder="3" class="form-input" required>
        </td>
        <td>
            <select name="grade[]" class="form-select" required>
                <option value="">Select Grade</option>
                <option value="A">A (Excellent)</option>
                <option value="B+">B+ (Very Good)</option>
                <option value="B">B (Good)</option>
                <option value="C+">C+ (Fairly Good)</option>
                <option value="C">C (Average)</option>
                <option value="D">D (Pass)</option>
                <option value="E">E (Fail)</option>
                <option value="F">F (Fail)</option>
            </select>
        </td>
        <td>
            <button type="button" class="btn-remove" onclick="removeCourse(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    return row;
}

function removeCourse(button) {
    const row = button.closest('tr');
    const tableBody = document.getElementById('courseTableBody');
    
    if (tableBody.children.length <= 1) {
        return; // Don't remove the last row
    }
    
    // Add removing animation
    row.classList.add('removing');
    
    // Remove the row after animation
    setTimeout(() => {
        row.remove();
        updateRemoveButtons();
    }, 300);
}

function updateRemoveButtons() {
    const tableBody = document.getElementById('courseTableBody');
    const removeButtons = tableBody.querySelectorAll('.btn-remove');
    
    // Disable remove button if only one row exists
    removeButtons.forEach(button => {
        button.disabled = tableBody.children.length <= 1;
    });
}

function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const button = e.target.querySelector('button[type="submit"]');
    
    // Add loading state
    button.classList.add('loading');
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating...';
    
    // Simulate form submission to PHP
    fetch('calculate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        displayResults(data);
    })
    .catch(error => {
        console.error('Error:', error);
        // For demo purposes, calculate on frontend
        calculateCGPAFrontend(formData);
    })
    .finally(() => {
        // Remove loading state
        button.classList.remove('loading');
        button.innerHTML = '<i class="fas fa-calculator"></i> Calculate CGPA';
    });
}

function calculateCGPAFrontend(formData) {
    const courses = [];
    const courseNames = formData.getAll('course_name[]');
    const creditHours = formData.getAll('credit_hours[]');
    const grades = formData.getAll('grade[]');
    
    let totalGradePoints = 0;
    let totalCredits = 0;
    
    const gradeValues = {
        'A': 4.0, 'B+': 3.5, 'B': 3.0, 'C+': 2.5,
        'C': 2.0, 'D': 1.0, 'E': 0.0, 'F': 0.0
    };
    
    for (let i = 0; i < courseNames.length; i++) {
        const credits = parseFloat(creditHours[i]);
        const gradePoint = gradeValues[grades[i]];
        const courseGradePoints = credits * gradePoint;
        
        courses.push({
            name: courseNames[i],
            credits: credits,
            grade: grades[i],
            gradePoint: gradePoint,
            courseGradePoints: courseGradePoints
        });
        
        totalCredits += credits;
        totalGradePoints += courseGradePoints;
    }
    
    const cgpa = totalCredits > 0 ? (totalGradePoints / totalCredits) : 0;
    const classification = getClassification(cgpa);
    
    const results = {
        success: true,
        cgpa: cgpa.toFixed(2),
        classification: classification,
        totalCredits: totalCredits,
        totalGradePoints: totalGradePoints.toFixed(2),
        courses: courses,
        targetAdvice: getTargetAdvice(cgpa)
    };
    
    displayResults(results);
}

function getClassification(cgpa) {
    if (cgpa >= 3.6) return { name: 'First Class', color: '#ffd700', icon: 'fas fa-crown' };
    if (cgpa >= 3.0) return { name: 'Second Class Upper', color: '#00c851', icon: 'fas fa-medal' };
    if (cgpa >= 2.5) return { name: 'Second Class Lower', color: '#39c0ed', icon: 'fas fa-award' };
    if (cgpa >= 2.0) return { name: 'Third Class', color: '#ffbb33', icon: 'fas fa-certificate' };
    if (cgpa >= 1.0) return { name: 'Pass', color: '#ff8800', icon: 'fas fa-check' };
    return { name: 'Fail (No Award)', color: '#dc3545', icon: 'fas fa-times' };
}

function getTargetAdvice(cgpa) {
    if (cgpa < 3.6) {
        return {
            show: true,
            message: `To achieve First Class (3.6+ CGPA), you'll need to maintain higher grades in your remaining courses.`,
            target: 'First Class'
        };
    }
    return { show: false };
}

function displayResults(data) {
    if (!data.success) {
        alert('Error calculating CGPA. Please check your inputs.');
        return;
    }
    
    const modalResults = document.getElementById('modalResults');
    const classification = data.classification;
    
    modalResults.innerHTML = `
        <div class="result-display">
            <div class="cgpa-display">
                <div class="cgpa-value">${data.cgpa}</div>
                <div class="cgpa-label">Your CGPA</div>
            </div>
            
            <div class="class-display" style="background: ${classification.color};">
                <i class="${classification.icon}"></i>
                ${classification.name}
            </div>
            
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: 600; color: #667eea;">${data.totalCredits}</div>
                    <div style="font-size: 0.9rem; color: #666;">Total Credits</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: 600; color: #764ba2;">${data.totalGradePoints}</div>
                    <div style="font-size: 0.9rem; color: #666;">Grade Points</div>
                </div>
            </div>
            
            ${data.targetAdvice && data.targetAdvice.show ? `
                <div class="target-advice" style="background: linear-gradient(135deg, #fa709a, #fee140); color: #fff; padding: 20px; border-radius: 12px; margin: 20px 0;">
                    <h4><i class="fas fa-lightbulb"></i> Improvement Tip</h4>
                    <p>${data.targetAdvice.message}</p>
                </div>
            ` : ''}
            
            <div class="course-breakdown">
                <h4><i class="fas fa-list"></i> Course Breakdown</h4>
                <div class="course-list">
                    ${data.courses.map(course => `
                        <div class="course-item">
                            <div class="course-name">${course.name}</div>
                            <div class="course-details">
                                <span>${course.credits} credits</span>
                                <span>Grade: ${course.grade}</span>
                                <span>${course.gradePoint.toFixed(1)} points</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('resultModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('resultModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function predictTargetGPA() {
    const currentCGPA = parseFloat(document.getElementById('currentCGPA').value);
    const remainingCredits = parseFloat(document.getElementById('remainingCredits').value);
    const targetClass = document.getElementById('targetClass').value;
    
    if (!currentCGPA || !remainingCredits || !targetClass) {
        alert('Please fill in all fields for target prediction.');
        return;
    }
    
    const targetRanges = {
        'first': 3.6,
        'second_upper': 3.0,
        'second_lower': 2.5,
        'third': 2.0
    };
    
    const targetCGPA = targetRanges[targetClass];
    
    if (currentCGPA >= targetCGPA) {
        showPredictionResult(`Great! You've already achieved the ${getTargetClassName(targetClass)} range. Keep maintaining your current performance!`, 'success');
        return;
    }
    
    // Calculate required GPA for remaining courses
    // Formula: (currentCGPA * currentCredits + requiredGPA * remainingCredits) / totalCredits = targetCGPA
    // Assuming current credits based on typical program structure
    const estimatedCurrentCredits = 60; // This could be made dynamic
    const totalCredits = estimatedCurrentCredits + remainingCredits;
    const requiredGPA = (targetCGPA * totalCredits - currentCGPA * estimatedCurrentCredits) / remainingCredits;
    
    if (requiredGPA > 4.0) {
        showPredictionResult(`Unfortunately, it's mathematically impossible to reach ${getTargetClassName(targetClass)} with your current CGPA and remaining credits. Consider aiming for the next achievable target.`, 'warning');
    } else if (requiredGPA < 0) {
        showPredictionResult(`Good news! You can achieve ${getTargetClassName(targetClass)} with any grades in your remaining courses!`, 'success');
    } else {
        showPredictionResult(`To achieve ${getTargetClassName(targetClass)}, you need to maintain an average GPA of <strong>${requiredGPA.toFixed(2)}</strong> in your remaining ${remainingCredits} credit hours.`, 'info');
    }
}

function getTargetClassName(targetClass) {
    const names = {
        'first': 'First Class',
        'second_upper': 'Second Class Upper',
        'second_lower': 'Second Class Lower',
        'third': 'Third Class'
    };
    return names[targetClass] || targetClass;
}

function showPredictionResult(message, type) {
    const resultDiv = document.getElementById('predictionResult');
    const colors = {
        'success': 'linear-gradient(135deg, #00c851, #39c0ed)',
        'warning': 'linear-gradient(135deg, #ffbb33, #ff8800)',
        'info': 'linear-gradient(135deg, #667eea, #764ba2)'
    };
    
    resultDiv.innerHTML = `
        <div style="background: ${colors[type]}; color: #fff; padding: 25px; border-radius: 12px; text-align: center;">
            <h3><i class="fas fa-target"></i> Prediction Result</h3>
            <p style="font-size: 1.1rem; margin-top: 15px;">${message}</p>
        </div>
    `;
    
    resultDiv.classList.remove('hidden');
    
    // Smooth scroll to result
    resultDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Add some interactive enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to grade items
    const gradeItems = document.querySelectorAll('.grade-item');
    gradeItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Add click effect to class items
    const classItems = document.querySelectorAll('.class-item');
    classItems.forEach(item => {
        item.addEventListener('click', function() {
            const range = this.querySelector('.class-range').textContent;
            const name = this.querySelector('.class-name').textContent;
            
            // Show a tooltip or highlight effect
            const tooltip = document.createElement('div');
            tooltip.innerHTML = `<strong>${name}</strong><br>CGPA Range: ${range}`;
            tooltip.style.cssText = `
                position: absolute;
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 10px 15px;
                border-radius: 8px;
                font-size: 0.9rem;
                pointer-events: none;
                z-index: 1000;
                top: -60px;
                left: 50%;
                transform: translateX(-50%);
                white-space: nowrap;
            `;
            
            this.style.position = 'relative';
            this.appendChild(tooltip);
            
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }, 2000);
        });
    });
});