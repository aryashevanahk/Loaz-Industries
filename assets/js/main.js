// Global JavaScript Functions

$(document).ready(function() {
    // Add active class to current nav item
    const currentLocation = window.location.pathname;
    $('.nav-link').each(function() {
        const link = $(this).attr('href');
        if (link && currentLocation.indexOf(link) !== -1) {
            $(this).addClass('active');
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
    
    // Add scroll to top button
    $('body').append('<button class="btn btn-primary scroll-top" style="position: fixed; bottom: 20px; right: 20px; display: none; border-radius: 50%; width: 50px; height: 50px;"><i class="fas fa-arrow-up"></i></button>');
    
    $(window).scroll(function() {
        if ($(this).scrollTop() > 100) {
            $('.scroll-top').fadeIn();
        } else {
            $('.scroll-top').fadeOut();
        }
    });
    
    $('.scroll-top').click(function() {
        $('html, body').animate({scrollTop: 0}, 800);
        return false;
    });
    
    // Form validation
    $('form[data-validate="true"]').on('submit', function(e) {
        let isValid = true;
        $(this).find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('is-invalid');
                showError($(this), 'Field ini harus diisi');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showNotification('Mohon lengkapi semua field yang diperlukan', 'danger');
        }
    });
    
    // Real-time validation
    $('[required]').on('blur', function() {
        if (!$(this).val()) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    
    // Show error message
    function showError(element, message) {
        let errorDiv = element.next('.invalid-feedback');
        if (!errorDiv.length) {
            errorDiv = $('<div class="invalid-feedback"></div>');
            element.after(errorDiv);
        }
        errorDiv.text(message);
    }
    
    // Show notification
    window.showNotification = function(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3" 
                 style="z-index: 9999; min-width: 300px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);" 
                 role="alert">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle')} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        $('body').append(alertHtml);
        
        setTimeout(function() {
            $('.alert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 3000);
    };
    
    // Confirm delete
    window.confirmDelete = function(url, message = 'Apakah Anda yakin?') {
        if (confirm(message)) {
            window.location.href = url;
        }
        return false;
    };
    
    // Loading spinner
    window.showLoading = function() {
        if ($('#loading-spinner').length === 0) {
            $('body').append(`
                <div id="loading-spinner" class="spinner-overlay">
                    <div class="spinner"></div>
                </div>
            `);
        }
        $('#loading-spinner').fadeIn();
    };
    
    window.hideLoading = function() {
        $('#loading-spinner').fadeOut();
    };
});