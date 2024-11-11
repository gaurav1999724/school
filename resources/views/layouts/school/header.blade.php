<header class="navbar">
    <div class="container">
        <div class="navbarWrapper">
            <div class="navLogoWrapper">
                <div class="navLogo">
                    <a href="{{ url('/') }}">
                        <img src="" class="nav-logo companyLogo" alt="" />
                    </a>
                </div>
            </div>
            <div class="menuListWrapper">
                <ul class="listItems">
                    <li>
                        <a href="{{ url('/') }}">Home</a>
                    </li>
                    @if((isset($schoolSettings['about_us_status']) && $schoolSettings['about_us_status'] == 1) || 
                    (isset($schoolSettings['our_mission_status']) && $schoolSettings['our_mission_status'] == 1))
                        <li>
                            <a href="{{ url('school/about-us') }}">About Us</a>
                        </li>
                    @endif
                    @if (isset($schoolSettings['gallery_status']) && $schoolSettings['gallery_status'] == 1)
                        <li>
                            <div class="dropdown">
                                <a class="btn btn-secondary dropdown-toggle" href="#" role="button"
                                    id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                                    Gallery
                                </a>

                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                                    <li>
                                        <a class="dropdown-item" href="{{ url('school/photos') }}">Photos</a>
                                    </li>
                                    <hr />
                                    <li>
                                        <a class="dropdown-item" href="{{ url('school/videos') }}">Videos</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    @endif
                    @if(isset($schoolSettings['faqs_status']) && $schoolSettings['faqs_status'] == 1 )
                        <li>
                            <a href="{{ url('/#faqs') }}">FAQs</a>
                        </li>
                    @endif
                    @if(isset($schoolSettings['contact_us_status']) && $schoolSettings['contact_us_status'] == 1 )     
                        <li>
                            <a href="{{ url('school/contact-us') }}">Contact Us</a>
                        </li>
                    @endif
                </ul>
                <div class="hamburg">
                    <span data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight"><i
                            class="fa-solid fa-bars"></i></span>
                </div>
            </div>
            <div class="loginWrapper">
                @if(isset($schoolSettings['online_registration_status']) && $schoolSettings['online_registration_status'] == 1)
                    <button class="commonBtn admissionBtn">
                        <div class="default-btn">
                            <a href="{{ route('online-admission.index') }}">Admission Open</a>
                        </div>
                        <div class="hover-btn">
                            <a href="{{ route('online-admission.index') }}">Apply Now</a>
                        </div>
                    </button>
                    @endif
                <button class="commonBtn redirect-login">Login <i class="fa-regular fa-user"></i></button>
            </div>
        </div>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
            <div class="offcanvas-header">
                <div class="navLogoWrapper">
                    <div class="navLogo">
                        <img src="" alt="" class="nav-logo" />
                    </div>
                </div>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="listItems">
                    <li>
                        <a href="{{ url('/') }}">Home</a>
                    </li>
                    @if((isset($schoolSettings['about_us_status']) && $schoolSettings['about_us_status'] == 1) || 
                    (isset($schoolSettings['our_mission_status']) && $schoolSettings['our_mission_status'] == 1))
                        <li>
                            <a href="{{ url('school/about-us') }}">About Us</a>
                        </li>
                    @endif
                    @if (isset($schoolSettings['gallery_status']) && $schoolSettings['gallery_status'] == 1)
                        <li>
                            <div class="dropdown">
                                <a class="btn btn-secondary dropdown-toggle" href="#" role="button"
                                    id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                                    Gallery
                                </a>

                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                                    <li>
                                        <a class="dropdown-item" href="{{ url('school/photos') }}">Photos</a>
                                    </li>
                                    <hr />
                                    <li>
                                        <a class="dropdown-item" href="{{ url('school/videos') }}">Videos</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    @endif
                    @if(isset($schoolSettings['faqs_status']) && $schoolSettings['faqs_status'] == 1 )
                        <li>
                            <a href="#faqs">FAQs</a>
                        </li>
                    @endif
                    @if(isset($schoolSettings['contact_us_status']) && $schoolSettings['contact_us_status'] == 1 )    
                        <li>
                            <a href="{{ url('school/contact-us') }}">Contact Us</a>
                        </li>
                    @endif
                    <div class="loginWrapper">
                        @if(isset($schoolSettings['online_registration_status']) && $schoolSettings['online_registration_status'] == 1)
                            <button class="commonBtn admissionBtn">
                                <div class="default-btn">
                                    <a href="{{ route('online-admission.index') }}">Admission Open</a>
                                </div>
                                <div class="hover-btn">
                                    <a href="{{ route('online-admission.index') }}">Apply Now</a>
                                </div>
                            </button>
                        @endif
                        <button class="commonBtn redirect-login">Login <i class="fa-regular fa-user"></i></button>
                    </div>
                </ul>
            </div>
        </div>
    </div>
</header>
