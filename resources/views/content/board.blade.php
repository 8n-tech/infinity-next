@extends('layouts.main.board')

@section('content')
<main class="board-index index-threaded mode-{{ $reply_to ? "reply" : "index" }} @if (isset($page)) page-{{ $page }} @endif">
	
	<section class="index-form">
		@include('content.board.post.form', [
			'board'   => &$app['app.board'],
			'actions' => [ $reply_to ? "reply" : "thread" ],
		])
	</section>
	
	@include('nav.board.pages', [
		'showCatalog' => true,
		'showIndex'   => !!$reply_to,
		'showPages'   => false,
	])
	
	<section class="index-threads">
		@include( 'widgets.ads.board_top_left' )
		
		<ul class="thread-list">
			@foreach ($posts as $thread)
			<li class="thread-item">
				<article class="thread">
					@include('content.board.thread', [
						'board'   => &$app['app.board'],
						'thread'  => $thread,
						'op'      => $thread,
					])
				</article>
			</li>
			@endforeach
		</ul>
	</section>
	
	@include('content.board.sidebar')
	
</main>
@stop

@section('footer-inner')
	@include('nav.board.pages', [
		'showCatalog' => true,
		'showIndex'   => !!$reply_to,
		'showPages'   => true,
	])
@stop