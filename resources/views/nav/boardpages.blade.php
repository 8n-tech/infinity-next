<nav class="pagination">
	<div class="pagination-buttons buttons-before">
		@if ($page > 1)
			<a class="button pagination-button pagination-first" href="{{{url($board->uri)}}}">&lt;&lt;</a>
		@else
			<button class="pagination-button pagination-first" disabled>&lt;&lt;</button>
		@endif
		
		@if ($pagePrev !== false)
			<a class="button pagination-button pagination-prev" href="{{{url("{$board->uri}/{$pagePrev}")}}}">&lt;</a>
		@else
			<button class="pagination-button pagination-prev" disabled>&lt;</button>
		@endif
	</div>
	
	<ul class="pagination-pages">
		@for ($i = 1; $i <= $pages; ++$i)
		<li class="pagination-page">
			<a class="pagination-link @if ($i == $page) pagination-active @endif" href="{{{url("{$board->uri}/{$i}")}}}">{{{$i}}}</a>
		</li>
		@endfor
	</ul>
	
	<div class="pagination-buttons buttons-after">
		@if ($pageNext !== false)
			<a class="button pagination-button pagination-next" href="{{{url("{$board->uri}/{$pageNext}")}}}">&gt;</a>
		@else
			<button class="pagination-button pagination-next" disabled>&gt;</button>
		@endif
		
		@if ($page < $pages)
			<a class="button pagination-button pagination-last" href="{{{url("{$board->uri}/{$pages}")}}}">&gt;&gt;</a>
		@else
			<button class="pagination-button pagination-last" disabled>&gt;&gt;</button>
		@endif
	</div>
</nav>