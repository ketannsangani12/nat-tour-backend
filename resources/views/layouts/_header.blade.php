<header>
	<div class="container">
		<nav class="navbar navbar-expand-lg navbar-light">
			<a class="navbar-brand" href="{{ route('home') }}">Home</a>
			
			<ul class="navbar-nav align-items-lg-center justify-content-lg-end">
@guest
				<li class="nav-item">
					<a class="nav-link" href="{{route('auth.customer.login-form')}}">Log-In</a>
				</li>

				<li class="nav-item">
					<a class="nav-link" href="{{route('auth.customer.register-form')}}">Register</a>
				</li>
@else
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle my-profile-menu" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">My Account</a>
					<div class="dropdown-menu white-bg" aria-labelledby="navbarDropdown">
						<a class="dropdown-item" href="{{ route('customer.edit-profile') }}">My Profile</a>
						<a class="dropdown-item" href="{{ route('customer.logout') }}" onclick="event.preventDefault();$('#logout-form').submit()">{{ __('Logout') }}</a>

						<form method="POST" action="{{ route('customer.logout') }}" id="logout-form" class="hidden">
							@csrf
						</form> 
					</div>
				</li>
@endif
			</ul>
		</nav>
	</div>
</header>
