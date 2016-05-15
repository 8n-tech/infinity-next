@extends('layouts.main')

@section('area-css')
	<link rel="stylesheet" href="{{ elixir('static/builds/panel.css') }}" />
@endsection

@set('hideTitles', true)

@section('content')
<main class="cp">
	<div class="cp-container grid-container">
		<div class="cp-box smooth-box grid-100 grid-parent">
			@section('nav-primary')
				@include( $c::$navPrimary )
			@show

			<div class="cp-frame grid-15">
				@section('nav-secondary')
					@include( $c::$navSecondary)
				@show
			</div>

			<div class="cp-frame grid-85">
				<div class="panel-actions">@yield('actions')</div>
				<h3 class="panel-title">@yield('title')</h3>

				@if (isset($c::$navTertiary))
				@section('nav-tertiary')
					@include( $c::$navTertiary)
				@show
				@endif

				@include('widgets.messages')

				@yield('body')
			</div>
		</div>
	</div>
</main>
@endsection
