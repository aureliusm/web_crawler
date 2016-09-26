@extends('layout')
@section('content')
    <div class="container">

        @if(!isset($search))
            <form action="{{ action('CrawlerController@scrape') }}" method="POST">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">

                <div class="row">
                    <div class="col-md-8 col-md-offset-1">
                        <input type="url" name="url" class="form-control" placeholder="Enter URL"/>
                        @if(isset($error))
                            <div class="alert alert-danger" style="margin-top:10px;">
                                {{ $error }}
                            </div>
                        @endif
                    </div>
                    <div class="col-md-1">
                        <button type="submit" name="search" id="search" class="btn btn-primary">SCRAPE</button>
                    </div>
                </div>
            </form>
        @endif

        @if(isset($search))
            <a href="{{ action('CrawlerController@index') }}" class="btn btn-info">< Back</a>
            <br>

            <div class="row">
                <div class="col-md-6">
                    <h3>Scrape of URL: {{ $search->url }}</h3>

                    <p>Found resources: {{ $search->resources->count() }}</p>
                </div>
                <form action="{{ action('CrawlerController@search') }}" method="GET">
                    <input type="hidden" name="url" value="{{ $search->url }}"/>

                    <div class="col-md-4">
                        <br>
                        <input type="text" name="keywords" value="" placeholder="Enter keywords" class="form-control"/>
                    </div>
                    <div class="col-md-2">
                        <br>
                        <button type="submit" class="btn btn-success">Search</button>
                    </div>
                </form>
            </div>

            <hr>

            @foreach($search->resources AS $resource)
                @if($resource->type == 'meta')
                    <div class="alert alert-info" style="margin-top:50px;">{{ $resource->url }}</div>
                    <table class="table table-bordered">
                        <tr>
                            <th>URL</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>OG Title</th>
                            <th>OG Description</th>
                            <th>Language</th>
                        </tr>
                        <tr>
                            <td>{{ $resource->url }}</td>
                            <td>{{ $resource->title }}</td>
                            <td>{{ $resource->description }}</td>
                            <td>{{ $resource->og_title }}</td>
                            <td>{{ $resource->og_description }}</td>
                            <td>{{ $resource->language }}</td>
                        </tr>
                    </table>
                    <br>
                    <h3>Images</h3>
                    <table class="table table-bordered">
                        <tr>
                            <th>URL</th>
                            <th>File type</th>
                            <th>File size</th>
                            <th>File dimensions</th>
                        </tr>
                        @foreach($resource->resources AS $subresource)
                            @if($subresource->type == 'image')
                                <tr>
                                    <td>{{ $subresource->url }}</td>
                                    <td>{{ $subresource->file_type }}</td>
                                    <td>{{ $subresource->file_size }}</td>
                                    <td>{{ $subresource->file_dimensions }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
                    <br>
                    <h3>Errors (404, 500 ...)</h3>
                    <table class="table table-bordered">
                        <tr>
                            <th>URL</th>
                            <th>Error type</th>
                        </tr>
                        @foreach($resource->resources AS $subresource)
                            @if($subresource->type == 'error')
                                <tr>
                                    <td>{{ $subresource->url }}</td>
                                    <td>{{ $subresource->title }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
                    <hr>
                @endif
            @endforeach
        @endif

    </div>

@endsection