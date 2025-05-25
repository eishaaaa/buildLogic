<!DOCTYPE html>
<html lang="en">
<?php
session_start();
// Redirect to login if not authenticated or not a customer
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

include 'header.php';
?>

<section class="hero">
    <div class="swiper">
        <div class="swiper-wrapper">
            <div class="swiper-slide" style="background-image: url('https://dropinblog.net/34246798/files/featured/Home_Interior__Budget_Friendly_Once_for_Every_Homeowner.png');">
                <h1>Design Your Dream Home</h1>
            </div>
            <div class="swiper-slide" style="background-image: url('https://media.designcafe.com/wp-content/uploads/2023/01/31151510/contemporary-interior-design-ideas-for-your-home.jpg');">
                <h1>Create Stunning Designs</h1>
            </div>
            <div class="swiper-slide" style="background-image: url('https://img.freepik.com/free-photo/armchair-green-living-room-with-copy-space_43614-910.jpg');">
                <h1>Bring Your Ideas to Life</h1>
            </div>
        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
</section>


<section id="features" class="text-center py-5">
    <div class="container">
        <h2 class="mb-4">Features</h2>
        <p class="text-muted">Discover the powerful tools that help bring your dream home to life.</p>
        <div class="row">


            <div class="col-md-4">
                <a href="design.php" style="text-decoration: none; color: inherit;">
                    <div class="feature-card">
                        <img src="images/Design.png" alt="AI Home Design" class="feature-icon">
                        <h3>Design</h3>
                        <p>Use AI-driven tools to automate and refine designs.</p>
                        <button class="hidden-button" onclick="event.stopPropagation(); window.open('/case/ai-home-design', '_blank')">Step 2</button>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="budget.php" style="text-decoration: none; color: inherit;">
                    <div class="feature-card">
                        <img src="images/Budget.png" alt="3D Home Render" class="feature-icon">
                        <h3>Breakdown</h3>
                        <p>Convert your house budget into divisions.</p>
                        <button class="hidden-button" onclick="event.stopPropagation(); window.open('/case/2d-render-home', '_blank')">Step 3</button>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="selectmaterial.php" style="text-decoration: none; color: inherit;">
                    <div class="feature-card">
                        <img src="images/material.png" alt="AI Home Design" class="feature-icon">
                        <h3>Select Material</h3>
                        <p>Suggest material according to location & budget</p>
                        <button class="hidden-button" onclick="event.stopPropagation(); window.open('/case/ai-home-design', '_blank')">Step 4</button>
                    </div>
                </a>
            </div>

        </div>
    </div>
</section>

<section id="gallery" class="text-center py-5">
    <div class="container">
        <h2>Gallery</h2>
        <div class="row">
            <div class="col-md-3 gallery-item">
                <img src="https://static.planner5d.com/assets/images/home/gallery/1.webp" alt="Living Room" class="img-fluid">
                <p>Beautiful Living Room</p>
            </div>
            <div class="col-md-3 gallery-item">
                <img src="https://static.planner5d.com/assets/images/home/gallery/2.webp" alt="Kitchen" class="img-fluid">
                <p>Modern Kitchen</p>
            </div>
            <div class="col-md-3 gallery-item">
                <img src="https://static.planner5d.com/assets/images/home/gallery/3.webp" alt="Bedroom" class="img-fluid">
                <p>Cozy Bedroom</p>
            </div>
            <div class="col-md-3 gallery-item">
                <img src="https://static.planner5d.com/assets/images/home/gallery/4.webp" alt="Bathroom" class="img-fluid">
                <p>Stylish Bathroom</p>
            </div>
        </div>
    </div>
</section>


<section class="testimonials" id="testimonials" text-center py-5 style="background-color: #f8f9fa;">
    <div class="container">
        <h2 class="fw-bold">WHAT OUR CLIENTS SAY</h2>
        <p class="mb-5">We value our clients' feedback and constantly strive for excellence.</p>

        <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
            <div class="carousel-inner">
                <!-- Row 1 (3 Testimonials) -->
                <div class="carousel-item active">
                    <div class="row justify-content-center">
                        <div class="col-md-4">
                            <div class="testimonial-card p-4 rounded-3 shadow-sm bg-white">
                                <div class="testimonial-header">
                                    <img src="images/boy2.png" alt="David Smith" class="rounded-circle mb-3">
                                </div>
                                <h5 class="testimonial-name">David Smith</h5>
                                <p class="testimonial-role text-muted">CEO, SS Multimedia</p>
                                <p class="testimonial-text">
                                    <i class="fas fa-quote-left text-primary"></i> Outstanding service! The team was professional, efficient, and exceeded expectations.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4 d-none d-md-block">
                            <div class="testimonial-card p-4 rounded-3 shadow-sm bg-white">
                                <div class="testimonial-header">
                                    <img src="images/boy3.png" alt="Stefen Carman" class="rounded-circle mb-3">
                                </div>
                                <h5 class="testimonial-name">Stefen Carman</h5>
                                <p class="testimonial-role text-muted">Chairman, GH Group</p>
                                <p class="testimonial-text">
                                    <i class="fas fa-quote-left text-primary"></i> The professionalism and dedication of the team were truly remarkable. Highly recommended!
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4 d-none d-md-block">
                            <div class="testimonial-card p-4 rounded-3 shadow-sm bg-white">
                                <div class="testimonial-header">
                                    <img src="images/boy1.png" alt="Gary Brent" class="rounded-circle mb-3">
                                </div>
                                <h5 class="testimonial-name">Gary Brent</h5>
                                <p class="testimonial-role text-muted">CFO, XYZ IT Solutions</p>
                                <p class="testimonial-text">
                                    <i class="fas fa-quote-left text-primary"></i> Amazing experience! Their attention to detail and quality is simply unmatched.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Row 2 (Next 3 Testimonials) -->
                <div class="carousel-item">
                    <div class="row justify-content-center">
                        <div class="col-md-4">
                            <div class="testimonial-card p-4 rounded-3 shadow-sm bg-white">
                                <div class="testimonial-header">
                                    <img src="images/girl1.png" alt="Sophia Brown" class="rounded-circle mb-3">
                                </div>
                                <h5 class="testimonial-name">Sophia Brown</h5>
                                <p class="testimonial-role text-muted">CTO, Alpha Tech</p>
                                <p class="testimonial-text">
                                    <i class="fas fa-quote-left text-primary"></i> Exceptional customer service and brilliant solutions!
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4 d-none d-md-block">
                            <div class="testimonial-card p-4 rounded-3 shadow-sm bg-white">
                                <div class="testimonial-header">
                                    <img src="images/boy4.png" alt="Liam Carter" class="rounded-circle mb-3">
                                </div>
                                <h5 class="testimonial-name">Liam Carter</h5>
                                <p class="testimonial-role text-muted">Founder, Carter Co.</p>
                                <p class="testimonial-text">
                                    <i class="fas fa-quote-left text-primary"></i> Fantastic experience! Will definitely return for more.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4 d-none d-md-block">
                            <div class="testimonial-card p-4 rounded-3 shadow-sm bg-white">
                                <div class="testimonial-header">
                                    <img src="images/girl2.png" alt="Emily Davis" class="rounded-circle mb-3">
                                </div>
                                <h5 class="testimonial-name">Emily Davis</h5>
                                <p class="testimonial-role text-muted">Marketing Head, Innovate Inc.</p>
                                <p class="testimonial-text">
                                    <i class="fas fa-quote-left text-primary"></i> Their expertise and commitment are outstanding.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Small Arrow Controls -->
            <button class="carousel-control-prev custom-arrow" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="carousel-control-next custom-arrow" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
                <i class="fas fa-chevron-right"></i>
            </button>
            <!-- Carousel Indicators -->
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#testimonialCarousel" data-bs-slide-to="0" class="active"></button>
                <button type="button" data-bs-target="#testimonialCarousel" data-bs-slide-to="1"></button>
            </div>
        </div>
    </div>
</section>
<div id="faq" class="faq-container">
    <!-- Image on the left -->
    <div class="faq-image">
        <img src="images/faqs.png" alt="Construction Worker">
    </div>

    <!-- FAQ Section -->
    <div class="faq-content">
        <div class="faq-title">Frequently Asked Questions?</div>
        <!-- FAQ Items -->
        <div class="faq-item">
            <button class="faq-question">
                Is Brick Logic free to use?
                <span class="icon">+</span>
            </button>
            <div class="faq-answer">
                We offer both free features (like basic house plan suggestions) and premium features (like smart recommendations, full plan editor, and supplier connections). You can start for free and upgrade anytime!
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                How does the auto house plan generator work?
                <span class="icon">+</span>
            </button>
            <div class="faq-answer">
                Our intelligent system takes basic user input like plot size, number of rooms, and desired layout preferences to generate customized house plan suggestions using predefined templates and rules.
            </div>
        </div>

        <div class="faq-item">
            <button class="faq-question">
                Can I customize my house plans after generation?
                <span class="icon">+</span>
            </button>
            <div class="faq-answer">
                Yes! Once a proposal is generated, you can open it in our interactive floor plan editor to make adjustments, add rooms, resize spaces, and personalize the design to match your needs.
            </div>
        </div>

        <div class="faq-item">
            <button class="faq-question">
                How does the smart material recommendation system work?
                <span class="icon">+</span>
            </button>
            <div class="faq-answer">
                Based on your project's location, weather conditions, and construction type, our platform recommends the most suitable materials (e.g., bricks, cement, insulation) that are cost-effective, durable, and efficient.
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                Can I find local construction suppliers on Brick Logic?
                <span class="icon">+</span>
            </button>
            <div class="faq-answer">
                Absolutely! Brick Logic connects you with registered local material suppliers. You can view their inventory, prices, and contact them directly through our platform.
            </div>
        </div>

    </div>

</div>
<?php include 'footer.php'; ?>


<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<!-- Include Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
<script>
    const swiper = new Swiper('.swiper', {
        loop: true,
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        autoplay: {
            delay: 3000,
            disableOnInteraction: false,
        },
        on: {
            slideChangeTransitionStart: function() {
                document.querySelectorAll('.swiper-slide h1').forEach(el => {
                    el.classList.remove('fadeInUp');
                    el.classList.add('fadeOutDown');
                });
            },
            slideChangeTransitionEnd: function() {
                const activeSlide = document.querySelector('.swiper-slide-active h1');
                if (activeSlide) {
                    activeSlide.classList.remove('fadeOutDown');
                    activeSlide.classList.add('fadeInUp');
                }
            }
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const contactSection = document.querySelector('.contact-section');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    contactSection.classList.add('appear');
                    observer.unobserve(contactSection);
                }
            });
        }, {
            threshold: 0.1
        });

        observer.observe(contactSection);
    });

    // JavaScript for FAQ Toggle
    document.querySelectorAll(".faq-question").forEach(button => {
        button.addEventListener("click", function() {
            const answer = this.nextElementSibling;
            const icon = this.querySelector(".icon");

            if (answer.style.display === "block") {
                answer.style.display = "none";
                icon.textContent = "+";
            } else {
                document.querySelectorAll(".faq-answer").forEach(ans => ans.style.display = "none");
                document.querySelectorAll(".faq-question .icon").forEach(i => i.textContent = "+");

                answer.style.display = "block";
                icon.textContent = "−";
            }
        });
    });

    function showAuth(option) {
        $('#authModal').modal('show');
        const signInFieldset = $('.signIn');
        const signUpFieldset = $('.signUp');
        if (option === 'signIn') {
            signInFieldset.show();
            signUpFieldset.hide();
        } else if (option === 'signUp') {
            signInFieldset.hide();
            signUpFieldset.show();
        }
    }

    function toggleAuthContainer() {
        const signInForm = document.querySelector('.signIn');
        const signUpForm = document.querySelector('.signUp');
        signInForm.style.display = signInForm.style.display === 'none' ? 'block' : 'none';
        signUpForm.style.display = signUpForm.style.display === 'none' ? 'block' : 'none';
    }

    function closeAuthModal() {
        $('#authModal').modal('hide');
    }
</script>

</html>