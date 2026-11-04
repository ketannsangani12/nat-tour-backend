@foreach($release as $key => $post)
    <div class="list-wrap d-lg-flex post">
        <div class="date-wrap text-lg-center">
            <div class="date">{{ date('d', strtotime($post->operation_at)) }}</div>
            <span class="month">{{ date('M, Y', strtotime($post->operation_at)) }}</span>
        </div>

        <div class="content-wrap">
            <div class="d-flex justify-content-between">
                <div class="list-title">
                    <a href="{{ route("$routePrefix.release.view",  $post->id) }}">{{ $post->title}} </a>
                </div>
            </div>

            <div class="list-subtitle">{{ $post->subtitle}}</div>

            <p class="list-text">
                {{ str_replace("&nbsp;","",strip_tags($post->body)) }}
            </p>
        </div>

        @if($post->image == "" && $post->video != "")
            <div class="img-wrap">
                <iframe width="200px" height="110" src="{{ $post->video }}"></iframe>
            </div>
        @endif

        @if($post->image != "")
            <div class="img-wrap">
                <img src="{{ $post->image }}" alt="">
            </div>
        @endif
    </div>
@endforeach
