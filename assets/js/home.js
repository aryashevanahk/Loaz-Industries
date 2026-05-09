// Homepage JavaScript
$(document).ready(function() {
    // Animate elements on scroll
    const animateOnScroll = function() {
        $('.part-card, .feature-card').each(function() {
            const elementTop = $(this).offset().top;
            const viewportBottom = $(window).scrollTop() + $(window).height();
            
            if (elementTop < viewportBottom - 50) {
                $(this).addClass('animate__animated animate__fadeInUp');
            }
        });
    };
    
    // Initial animation check
    animateOnScroll();
    
    // Check on scroll
    $(window).scroll(function() {
        animateOnScroll();
    });
    
    // Add to cart with animation
    $('.btn-buy').click(function(e) {
        e.preventDefault();
        const button = $(this);
        const originalText = button.html();
        
        button.html('<i class="fas fa-spinner fa-spin"></i> Adding...');
        button.prop('disabled', true);
        
        // Simulate add to cart
        setTimeout(function() {
            button.html('<i class="fas fa-check"></i> Added to Cart!');
            button.removeClass('btn-buy').addClass('btn-success');
            
            // Show notification
            showNotification('Item added to cart successfully!', 'success');
            
            // Reset button after 2 seconds
            setTimeout(function() {
                button.html(originalText);
                button.prop('disabled', false);
                button.removeClass('btn-success').addClass('btn-buy');
            }, 2000);
        }, 500);
    });
    
    // Show notification function
    function showNotification(message, type) {
        const notification = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3" 
                 style="z-index: 9999; min-width: 300px;" role="alert">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        $('body').append(notification);
        
        setTimeout(function() {
            $('.alert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Smooth scroll for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $(this.hash);
        
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 70
            }, 800);
        }
    });
    
    // Search functionality for parts (if search is added)
    $('#search-part').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('.part-card').each(function() {
            const partName = $(this).find('.card-title').text().toLowerCase();
            
            if (partName.indexOf(searchTerm) === -1) {
                $(this).fadeOut();
            } else {
                $(this).fadeIn();
            }
        });
    });
    
    // Counter animation for stats
    const animateCounter = function() {
        $('.stat-number').each(function() {
            const target = parseInt($(this).data('target'));
            let current = 0;
            const increment = target / 50;
            const element = $(this);
            
            const updateCounter = setInterval(function() {
                current += increment;
                if (current >= target) {
                    element.text(target.toLocaleString());
                    clearInterval(updateCounter);
                } else {
                    element.text(Math.floor(current).toLocaleString());
                }
            }, 20);
        });
    };
    
    // Trigger counter when stats come into view
    $(window).scroll(function() {
        const statsSection = $('.stats-section');
        if (statsSection.length) {
            const sectionTop = statsSection.offset().top;
            const viewportBottom = $(window).scrollTop() + $(window).height();
            
            if (sectionTop < viewportBottom - 100) {
                animateCounter();
                $(window).off('scroll'); // Only trigger once
            }
        }
    });
});