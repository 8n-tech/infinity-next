<?php namespace App;

use App\BoardAdventure;
use App\FileStorage;
use App\FileAttachment;
use App\PostCite;
use App\Contracts\PermissionUser;
use App\Observers\PostObserver;
use App\Services\ContentFormatter;
use App\Support\Geolocation;
use App\Support\IP;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Cache;
use DB;
use Input;
use File;
use Request;

use Event;
use App\Events\ThreadNewReply;

class Post extends Model {
	
	use \App\Traits\EloquentBinary;
	use SoftDeletes;
	
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'posts';
	
	/**
	 * The primary key that is used by ::get()
	 *
	 * @var string
	 */
	protected $primaryKey = 'post_id';
	
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'board_uri',
		'board_id',
		'reply_to',
		'reply_to_board_id',
		'reply_last',
		'bumped_last',
		
		'stickied',
		'stickied_at',
		'bumplocked_at',
		'locked_at',
		'featured_at',
		
		'author_ip',
		'author_ip_nulled_at',
		'author_id',
		'author_country',
		'capcode_id',
		'subject',
		'author',
		'email',
		
		'body',
		'body_parsed',
		'body_parsed_at',
		'body_html',
	];
	
	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = [
		// Post Items
		'author_ip',
		'body',
		'body_parsed',
		'body_parsed_at',
		'body_html',
		
		// Relationships
		'bans',
		'board',
		'cites',
		'citedBy',
		'citedPosts',
		'editor',
		'op',
		'replies',
		'reports',
	];
	
	/**
	 * Attributes which do not exist but should be appended to the JSON output.
	 *
	 * @var array
	 */
	protected $appends = ['content_raw', 'content_html'];
	
	/**
	 * Attributes which are automatically sent through a Carbon instance on load.
	 *
	 * @var array
	 */
	protected $dates = ['reply_last', 'bumped_last', 'created_at', 'updated_at', 'deleted_at', 'stickied_at', 'bumplocked_at', 'locked_at', 'body_parsed_at', 'author_ip_nulled_at'];
	
	/**
	 * Indicates if this model is currently being prepared for update or insert.
	 *
	 * @var boolean
	 */
	public $saving = false;
	
	
	public function attachments()
	{
		return $this->belongsToMany("\App\FileStorage", 'file_attachments', 'post_id', 'file_id')->withPivot('filename', 'is_spoiler', 'position');
	}
	
	public function attachmentLinks()
	{
		return $this->hasMany("\App\FileAttachment");
	}
	
	public function bans()
	{
		return $this->hasMany('\App\Ban', 'post_id');
	}
	
	public function board()
	{
		return $this->belongsTo('\App\Board', 'board_uri');
	}
	
	public function capcode()
	{
		return $this->hasOne('\App\Role', 'role_id', 'capcode_id');
	}
	
	public function cites()
	{
		return $this->hasMany('\App\PostCite', 'post_id');
	}
	
	public function citedBy()
	{
		return $this->hasMany('\App\PostCite', 'cite_id', 'post_id');
	}
	
	public function citedPosts()
	{
		return $this->belongsToMany("\App\Post", 'post_cites', 'post_id');
	}
	
	public function citedByPosts()
	{
		return $this->belongsToMany("\App\Post", 'post_cites', 'cite_id', 'post_id');
	}
	
	public function editor()
	{
		return $this->hasOne('\App\User', 'user_id', 'updated_by');
	}
	
	public function op()
	{
		return $this->belongsTo('\App\Post', 'reply_to', 'post_id');
	}
	
	public function replies()
	{
		return $this->hasMany('\App\Post', 'reply_to', 'post_id');
	}
	
	public function reports()
	{
		return $this->hasMany('\App\Report', 'post_id');
	}
	
	
	/**
	 * Ties database triggers to the model.
	 *
	 * @return void
	 */
	public static function boot()
	{
		parent::boot();
		static::observe(new PostObserver);
	}
	
	/**
	 * Determines if the user can bumplock this post
	 *
	 * @param  App\Contracts\PermissionUser  $user
	 * @return boolean
	 */
	public function canBumplock($user)
	{
		return $user->canBumplock($this);
	}
	
	/**
	 * Determines if the user can delete this post.
	 *
	 * @param  App\Contracts\PermissionUser  $user
	 * @return boolean
	 */
	public function canDelete($user)
	{
		return $user->canDelete($this);
	}
	
	/**
	 * Determines if the user can edit this post.
	 *
	 * @param  App\Contracts\PermissionUser  $user
	 * @return boolean
	 */
	public function canEdit($user)
	{
		return $user->canEdit($this);
	}
	
	/**
	 * Determines if the user can lock this post
	 *
	 * @param  App\Contracts\PermissionUser  $user
	 * @return boolean
	 */
	public function canLock($user)
	{
		return $user->canLock($this);
	}
	
	/**
	 * Determines if the user can reply to post, or if this thread is open to replies in general.
	 *
	 * @param  App\Contracts\PermissionUser|null  $user
	 * @return boolean
	 */
	public function canReply($user = null)
	{
		if (!is_null($user))
		{
			return $user->canReply($this);
		}
		
		return true;
	}
	
	/**
	 * Determines if the user can report this post to board owners.
	 *
	 * @param  App\Contracts\PermissionUser  $user
	 * @return boolean
	 */
	public function canReport($user)
	{
		return $user->canReport($this);
	}
	
	/**
	 * Determines if the user can report this post to site owners.
	 *
	 * @param  App\Contracts\PermissionUser  $user
	 * @return boolean
	 */
	public function canReportGlobally($user)
	{
		return $user->canReportGlobally($this);
	}
	
	/**
	 * Determines if the user can sticky or unsticky this post.
	 *
	 * @param  App\Contracts\PermissionUser  $user
	 * @return boolean
	 */
	public function canSticky($user)
	{
		return $user->canSticky($this);
	}
	
	
	/**
	 * Counts the number of currently related reports that can be promoted.
	 *
	 * @param  PermissionUser  $user
	 * @return int
	 */
	public function countReportsCanPromote(PermissionUser $user)
	{
		$count = 0;
		
		foreach ($this->reports as $report)
		{
			if ($report->canPromote($user))
			{
				++$count;
			}
		}
		
		return $count;
	}
	
	/**
	 * Counts the number of currently related reports that can be demoted.
	 *
	 * @param  PermissionUser  $user
	 * @return int
	 */
	public function countReportsCanDemote(PermissionUser $user)
	{
		$count = 0;
		
		foreach ($this->reports as $report)
		{
			if ($report->canDemote($user))
			{
				++$count;
			}
		}
		
		return $count;
	}
	
	
	/**
	 * Returns a small, unique code to identify an author in one thread.
	 *
	 * @return string
	 */
	public function makeAuthorId()
	{
		$hashParts = [];
		$hashParts[] = env('APP_KEY');
		$hashParts[] = $this->board_uri;
		$hashParts[] = $this->reply_to_board_id ?: $this->board_id;
		$hashParts[] = $this->author_ip;
		
		$hash = implode($hashParts, "-");
		$hash = hash('sha256', $hash);
		$hash = substr($hash, 12, 6);
		
		return $hash;
	}
	
	/**
	 * Turns the author id into a consistent color.
	 *
	 * @param  boolean  $asArray
	 * @return string  In the format of rgb(xxx,xxx,xxx) or as an array.
	 */
	public function getAuthorIdBackgroundColor($asArray = false)
	{
		$authorId = $this->author_id;
		$colors   = [];
		$colors[] = crc32(substr($authorId, 0, 2)) % 254 + 1;
		$colors[] = crc32(substr($authorId, 2, 2)) % 254 + 1;
		$colors[] = crc32(substr($authorId, 4, 2)) % 254 + 1;
		
		if ($asArray)
		{
			return $colors;
		}
		
		return "rgba(" . implode(",", $colors) . ",0.75)";
	}
	
	/**
	 * Takess the author id background color and determines if we need a white or black text color.
	 *
	 * @return string  In the format of rgba(xxx,xxx,xxx,x)
	 */
	public function getAuthorIdForegroundColor()
	{
		$colors = $this->getAuthorIdBackgroundColor(true);
		
		if (array_sum($colors) < 382)
		{
			return "rgb(255,255,255)";
		}
		
		foreach ($colors as $color)
		{
			if ($color > 200)
			{
				return "rgb(0,0,0)";
			}
		}
		
		return "rgb(0,0,0)";
	}
	
	/**
	 * Returns the raw input for a post for the JSON output.
	 *
	 * @return string
	 */
	public function getAuthorIdAttribute()
	{
		if ($this->board->getConfig('postsThreadId'))
		{
			return $this->attributes['author_id'];
		}
		
		return null;
	}
	
	/**
	 * Returns the fully rendered HTML content of this post.
	 *
	 * @param  boolean  $skipCache
	 * @return string
	 */
	public function getBodyFormatted($skipCache = false)
	{
		if (!$skipCache)
		{
			if (!is_null($this->body_html))
			{
				return $this->body_html;
			}
			
			if (!is_null($this->body_parsed))
			{
				return $this->body_parsed;
			}
		}
		
		$ContentFormatter     = new ContentFormatter();
		$this->body_parsed    = $ContentFormatter->formatPost($this);
		$this->body_parsed_at = $this->freshTimestamp();
		
		// We use an update here instead of just saving $post because, in this method
		// there will frequently be additional properties on this object that cannot
		// be saved. To make life easier, we just touch the object.
		static::where(['post_id' => $this->post_id])->update([
			'body_parsed'    => $this->body_parsed,
			'body_parsed_at' => $this->body_parsed_at,
		]);
		
		return $this->body_parsed;
	}
	
	/**
	 * Returns the raw input for a post for the JSON output.
	 *
	 * @return string
	 */
	public function getContentRawAttribute($value)
	{
		if (!$this->trashed())
		{
			return $this->attributes['body'];
		}
		else
		{
			return null;
		}
	}
	
	/**
	 * Returns the rendered interior HTML for a post for the JSON output.
	 *
	 * @return string
	 */
	public function getContentHtmlAttribute($value)
	{
		if (!$this->trashed())
		{
			return $this->getBodyFormatted();
		}
		else
		{
			return null;
		}
	}
	
	/**
	 * Returns a name for the country. This is usually the ISO 3166-1 alpha-2 code.
	 *
	 * @return  string|null
	 */
	public function getCountryCode()
	{
		if (!is_null($this->author_country))
		{
			if ($this->author_country == "")
			{
				return "unknown";
			}
			
			return $this->author_country;
		}
		
		return null;
	}
	
	/**
	 * Returns the fully rendered HTML of a post in the JSON output.
	 *
	 * @return string
	 */
	public function getHtmlAttribute()
	{
		if (!$this->trashed())
		{
			return \View::make('content.board.thread', [
					'board'    => $this->board,
					'thread'   => $this,
					'op'       => false,
					'reply_to' => $this->reply_to ?: $this->board_id,
			])->render();
		}
		
		return null;
	}
	
	/**
	 * Returns a relative URL for opening this post.
	 *
	 * @return string
	 */
	public function getURL()
	{
		$url = "/{$this->board_uri}/thread/";
		
		if ($this->reply_to_board_id)
		{
			$url .= "{$this->reply_to_board_id}#{$this->board_id}";
		}
		else
		{
			$url .= "{$this->board_id}";
		}
		
		return $url;
	}
	
	
	/**
	 * Determines if this is a bumpless post.
	 *
	 * @return boolean
	 */
	public function isBumpless()
	{
		if ($this->email == "sage")
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * Determines if this thread cannot be bumped.
	 *
	 * @return boolean
	 */
	public function isBumplocked()
	{
		return !is_null($this->bumplocked_at);
	}
	
	/**
	 * Determines if the post is made from the client's remote address.
	 *
	 * @return boolean
	 */
	public function isAuthoredByClient()
	{
		if (is_null($this->author_ip))
		{
			return false;
		}
		
		return new IP($this->author_ip) === new IP();
	}
	
	/**
	 * Determines if this is deleted.
	 *
	 * @return boolean
	 */
	public function isDeleted()
	{
		return !is_null($this->deleted_at);
	}
	
	/**
	 * Determines if this is the first reply in a thread.
	 *
	 * @return boolean
	 */
	public function isOp()
	{
		return is_null($this->reply_to);
	}
	
	/**
	 * Determines if this thread is locked.
	 *
	 * @return boolean
	 */
	public function isLocked()
	{
		return !is_null($this->locked_at);
	}
	
	/**
	 * Determines if this thread is stickied.
	 *
	 * @return boolean
	 */
	public function isStickied()
	{
		return !is_null($this->stickied_at);
	}
	
	/**
	 * Returns the author IP in a human-readable format.
	 *
	 * @return string
	 */
	public function getAuthorIpAsString()
	{
		if ($this->hasAuthorIp())
		{
			return$this->author_ip->toText();
		}
		
		return false;
	}
	
	public function getAuthorIpAttribute()
	{
		if ($this->attributes['author_ip'] instanceof IP)
		{
			return $this->attributes['author_ip'];
		}
		
		return new IP($this->attributes['author_ip']);
	}
	
	public function setAuthorIpAttribute($value)
	{
		if (!is_binary($value))
		{
			$ip = new IP($value);
			$value = $ip->getStart(true);
		}
		
		return $this->attributes['author_ip'] = $value;
	}
	
	/**
	 * Returns the bit size of the IP.
	 *
	 * @return int  (32 or 128)
	 */
	public function getAuthorIpBitSize()
	{
		if ($this->hasAuthorIp())
		{
			return strpos($this->getAuthorIpAsString(), ":") === false ? 32 : 128;
		}
		
		return false;
	}
	
	/**
	 * Returns a user-friendly list of ranges available for this IP.
	 *
	 * @return array
	 */
	public function getAuthorIpRangeOptions()
	{
		$bitsize = $this->getAuthorIpBitSize();
		$range   = range(0, $bitsize);
		$masks   = [];
		
		foreach ($range as $mask)
		{
			$affectedIps  = number_format(pow(2, $bitsize - $mask), 0);
			$masks[$mask] = trans_choice("board.ban.ip_range_{$bitsize}", $mask, [
				'mask' => $mask,
				'ips'  => $affectedIps
			]);
		}
		
		return $masks;
	}
	
	/**
	 * Returns the board model for this post.
	 *
	 * @return \App\Board
	 */
	public function getBoard()
	{
		return $this->board()
			->get()
			->first();
	}
	
	/**
	 * Returns a human-readable capcode string.
	 *
	 * @return string
	 */
	public function getCapcodeName()
	{
		if ($this->capcode_capcode)
		{
			return trans_choice((string) $this->capcode_capcode, 0);
		}
		else if ($this->capcode_id)
		{
			return $this->capcode->getCapcodeName();
		}
		
		return "";
	}
	
	/**
	 * Parses the post text for citations.
	 *
	 * @return Collection
	 */
	public function getCitesFromText()
	{
		return ContentFormatter::getCites($this);
	}
	
	/**
	 * Returns a SHA1 checksum for this post's text.
	 *
	 * @param  boolean Option. If return should be binary. Defaults false.
	 * @return string|binary
	 */
	public function getChecksum($binary = false)
	{
		$postBody  = $this->body;
		$postRobot = preg_replace('/\W+/', "", $postBody);
		$checksum  = sha1($postRobot, $binary);
		
		if ($binary)
		{
			return binary_sql($checksum);
		}
		
		return $checksum;
	}
	
	/**
	 * Returns the last post made by this user across the entire site.
	 *
	 * @param  string $ip
	 * @return \App\Post
	 */
	public static function getLastPostForIP($ip = null)
	{
		if (is_null($ip))
		{
			$ip = new IP;
		}
		
		return Post::whereAuthorIP($ip)
			->orderBy('created_at', 'desc')
			->take(1)
			->get()
			->first();
	}
	
	/**
	 * Returns the page on which this thread appears.
	 * If the post is a reply, it will return the page it appears on in the thread, which is always 1.
	 *
	 * @return \App\Post
	 */
	public function getPage()
	{
		if ($this->isOp())
		{
			$board          = $this->board()->with('settings')->get()->first();
			$visibleThreads = $board->threads()->op()->where('bumped_last', '>=', $this->bumped_last)->count();
			$threadsPerPage = (int) $board->getConfig('postsPerPage', 10);
			
			return floor(($visibleThreads - 1) / $threadsPerPage) + 1;
		}
		
		return 1;
	}
	
	/**
	 * Returns the post model for the most recently featured post.
	 *
	 * @param  int  $dayRange  Optional. Number of days at most that the last most featured post can be in. Defaults 3.
	 * @return \App\Post
	 */
	public static function getPostFeatured($dayRange = 3)
	{
		$oldestPossible = \Carbon\Carbon::now()->subDays($dayRange);
		
		return static::where('featured_at', '>=', $oldestPossible)
			->withEverything()
			->orderBy('featured_at', 'desc')
			->first();
	}
	
	/**
	 * Returns the post model using the board's URI and the post's local board ID.
	 *
	 * @param  string  $board_uri
	 * @param  integer  $board_id
	 * @return \App\Post
	 */
	public static function getPostForBoard($board_uri, $board_id)
	{
		return static::where([
				'board_uri' => $board_uri,
				'board_id' => $board_id,
			])
			->first();
	}
	
	/**
	 * Returns the model for this post's original post (what it is a reply to).
	 *
	 * @return \App\Post
	 */
	public function getOp()
	{
		return $this->op()
			->get()
			->first();
	}
	
	/**
	 * Returns a few posts for the front page.
	 *
	 * @param  int  $number  How many to pull.
	 * @param  boolean $sfwOnly  If we only want SFW boards.
	 * @return Collection  of static
	 */
	public static function getRecentPosts($number = 16, $sfwOnly = true)
	{
		return static::where('body', '<>', "")
			->whereHas('board', function($query) use ($sfwOnly) {
				$query->where('is_indexed', '=', true);
				$query->where('is_overboard', '=', true);
				
				if ($sfwOnly)
				{
					$query->where('is_worksafe', '=', true);
				}
			})
			->with('board')
			->limit($number)
			->orderBy('post_id', 'desc')
			->get();
	}
	
	/**
	 * Returns the latest reply to a post.
	 *
	 * @return Post|null
	 */
	public function getReplyLast()
	{
		return $this->replies()
			->orderBy('post_id', 'desc')
			->take(1)
			->get()
			->first();
	}
	
	/**
	 * Returns all replies to a post.
	 *
	 * @return \Illuminate\Database\Eloquent\Collection
	 */
	public function getReplies()
	{
		if (isset($this->replies))
		{
			return $this->replies;
		}
		
		return $this->replies()
			->withEverything()
			->orderBy('post_id', 'asc')
			->get();
	}
	
	/**
	 * Returns the last few replies to a thread for index views.
	 *
	 * @return \Illuminate\Database\Eloquent\Collection
	 */
	public function getRepliesForIndex()
	{
		return $this->replies()
			->forIndex()
			->get()
			->reverse();
	}
	
	/**
	 * Returns a set of posts for an update request.
	 *
	 * @param  Carbon  $sinceTime
	 * @param  Board  $board
	 * @param  Post  $thread
	 * @param  boolean  $includeHTML  If the posts should also have very large 'content_html' values.
	 * @return Collection  of Posts
	 */
	public static function getUpdates($sinceTime, Board $board, Post $thread, $includeHTML = false)
	{
		$posts = static::whereInUpdate($sinceTime, $board, $thread)->get();
		
		if ($includeHTML)
		{
			foreach ($posts as $post)
			{
				$post->setAppendHTML(true);
			}
		};
		
		return $posts;
	}
	
	/**
	 * Returns if this post has an attached IP address.
	 *
	 * @return boolean
	 */
	public function hasAuthorIp()
	{
		return $this->author_ip !== null;
	}
	
	/**
	 * Get the appends attribute.
	 * Not normally available to models, but required for API responses.
	 *
	 * @param  array $appends
	 * @return array
	 */
	public function getAppends()
	{
		return $this->appends;
	}
	
	
	/**
	 * Create a new model instance that is existing.
	 *
	 * @param  array  $attributes
	 * @param  \Illuminate\Database\Connection|null  $connection
	 * @return \Illuminate\Database\Eloquent\Model|static
	 */
	public function newFromBuilder($attributes = array(), $connection = NULL)
	{
		if (isset($attributes->author_ip) && $attributes->author_ip !== null && !($attributes->author_ip instanceof IP))
		{
			$attributes->author_ip = new IP($attributes->author_ip);
		}
		
		
		return parent::newFromBuilder($attributes);
	}
	
	
	/**
	 * Sets the value of $this->appends to the input.
	 * Not normally available to models, but required for API responses.
	 *
	 * @param  array $appends
	 * @return array
	 */
	public function setAppends(array $appends)
	{
		return $this->appends = $appends;
	}
	
	/**
	 * Quickly add html to the append list for this model.
	 *
	 * @param  boolean  $add  defaults true
	 * @return Post
	 */
	public function setAppendHTML($add = true)
	{
		$appends   = $this->getAppends();
		
		if ($add)
		{
			$appends[] = "html";
		}
		else if (($key = array_search("html", $appends)) !== false)
		{
			unset($appends[$key]);
		}
		
		$this->setAppends($appends);
		return $this;
	}
	
	/**
	 * Sets the bumplock property timestamp.
	 *
	 * @param  boolean  $bumplock
	 * @return \App\Post
	 */
	public function setBumplock($bumplock = true)
	{
		if ($bumplock)
		{
			$this->bumplocked_at = $this->freshTimestamp();
		}
		else
		{
			$this->bumplocked_at = null;
		}
		
		return $this;
	}
	
	/**
	 * Sets the deleted timestamp.
	 *
	 * @param  boolean  $delete
	 * @return \App\Post
	 */
	public function setDeleted($delete = true)
	{
		if ($delete)
		{
			$this->deleted_at = $this->freshTimestamp();
		}
		else
		{
			$this->deleted_at = null;
		}
		
		return $this;
	}
	
	/**
	 * Sets the locked property timestamp.
	 *
	 * @param  boolean  $lock
	 * @return \App\Post
	 */
	public function setLocked($lock = true)
	{
		if ($lock)
		{
			$this->locked_at = $this->freshTimestamp();
		}
		else
		{
			$this->locked_at = null;
		}
		
		return $this;
	}
	
	/**
	 * Sets the sticky property of a post and updates relevant timestamps.
	 *
	 * @param  boolean  $sticky
	 * @return \App\Post
	 */
	public function setSticky($sticky = true)
	{
		if ($sticky)
		{
			$this->stickied = true;
			$this->stickied_at = $this->freshTimestamp();
		}
		else
		{
			$this->stickied = false;
			$this->stickied_at = null;
		}
		
		return $this;
	}
	
	
	public function scopeAndAttachments($query)
	{
		return $query->with('attachments');
	}
	
	public function scopeAndFirstAttachment($query)
	{
		return $query->with(['attachments' => function($query)
		{
			$query->limit(1);
		}]);
	}
	
	public function scopeAndBans($query)
	{
		return $query->with(['bans' => function($query)
		{
			$query->orderBy('created_at', 'asc');
		}]);
	}
	
	public function scopeAndCapcode($query)
	{
		return $query
			->leftJoin('roles', function($join)
			{
				$join->on('roles.role_id', '=', 'posts.capcode_id');
			})
			->addSelect(
				'posts.*',
				'roles.capcode as capcode_capcode',
				'roles.role as capcode_role',
				'roles.name as capcode_name'
			);
	}
	
	public function scopeAndCites($query)
	{
		return $query->with('cites', 'cites.cite');
	}
	
	public function scopeAndEditor($query)
	{
		return $query
			->leftJoin('users', function($join)
			{
				$join->on('users.user_id', '=', 'posts.updated_by');
			})
			->addSelect(
				'posts.*',
				'users.username as updated_by_username'
			);
	}
	
	public function scopeAndReplies($query)
	{
		return $query->with(['replies' => function($query) {
			$query->withEverything();
		}]);
	}
	
	public function scopeAndPromotedReports($query)
	{
		return $query->with(['reports' => function($query) {
			$query->whereOpen();
			$query->wherePromoted();
		}]);
	}
	
	public function scopeWhereAuthorIP($query, $ip)
	{
		$ip = new IP($ip);
		return $query->where('author_ip', $ip->toSQL());
	}
	
	public function scopeIpString($query, $ip)
	{
		return $query->whereAuthorIP($ip);
	}
	
	public function scopeIpBinary($query, $ip)
	{
		return $query->whereAuthorIP($ip);
	}
	
	public function scopeOp($query)
	{
		return $query->where('reply_to', null);
	}
	
	public function scopeRecent($query)
	{
		return $query->where('created_at', '>=', static::freshTimestamp()->subHour());
	}
	
	public function scopeForIndex($query)
	{
		return $query->withEverything()
			->orderBy('post_id', 'desc');
			//->take( $this->stickied_at ? 1 : 5 );
	}
	
	public function scopeReplyTo($query, $replies = false)
	{
		if ($replies instanceof \Illuminate\Database\Eloquent\Collection)
		{
			$thread_ids = [];
			
			foreach ($replies as $thread)
			{
				$thread_ids[] = (int) $thread->post_id;
			}
			
			return $query->whereIn('reply_to', $thread_ids);
		}
		else if (is_numeric($replies))
		{
			return $query->where('reply_to', '=', $replies);
		}
		else
		{
			return $query->where('reply_to', 'not', null);
		}
	}
	
	public function scopeWithEverything($query)
	{
		return $query
			->andAttachments()
			->andBans()
			->andCapcode()
			->andCites()
			->andEditor()
			->andPromotedReports();
	}
	
	public function scopeWithEverythingAndReplies($query)
	{
		return $query->withEverything()
			->with(['replies' => function($query) {
				$query->withEverything();
			}]);
	}
	
	public function scopeWhereHasReports($query)
	{
		return $query->whereHas('reports', function($query)
			{
				$query->whereOpen();
			});
	}
	
	public function scopeWhereHasReportsFor($query, PermissionUser $user)
	{
		return $query->whereHas('reports', function($query) use ($user)
			{
				$query->whereOpen();
				$query->whereResponsibleFor($user);
			})
			->with(['reports' => function($query) use ($user) {
				$query->whereOpen();
				$query->whereResponsibleFor($user);
			}]);
	}
	
	/**
	 * Logic for pulling posts for API updates.
	 *
	 * @param  DbQuery  $query  Provided by Laravel.
	 * @param  Board  $board
	 * @param  Carbon  $sinceTime
	 * @param  Post   $thread  Board ID.
	 * @return $query
	 */
	public function scopeWhereInUpdate($query, $sinceTime, Board $board, Post $thread)
	{
			// Find posts in this board.
		return $query->where('posts.board_uri', $board->board_uri)
			// Fetch accessory tables too.
			->withEverything()
			// Only pull posts in this thread, or that is this thread.
			->where(function($query) use ($thread) {
				$query->where('posts.reply_to_board_id', $thread->board_id);
				$query->orWhere('posts.board_id', $thread->board_id);
			})
			// Nab posts that've been updated since our sinceTime.
			->where(function($query) use ($sinceTime) {
				$query->where('posts.updated_at', '>=', $sinceTime);
				$query->orWhere('posts.deleted_at', '>=', $sinceTime);
			})
			// Include deleted posts.
			->withTrashed()
			// Order by board id in reverse order (so they appear in the thread right).
			->orderBy('posts.board_id', 'asc');
	}
	
	
	/**
	 * Fetches a URL for either this thread or an action.
	 *
	 * @param  string  $action
	 * @return string
	 */
	public function url($action = null)
	{
		$url = "";
		
		if (is_null($action))
		{
			if ($this->reply_to_board_id)
			{
				$url = "/{$this->board_uri}/thread/{$this->reply_to_board_id}#{$this->board_id}";
			}
			else
			{
				$url = "/{$this->board_uri}/thread/{$this->board_id}";
			}
		}
		else
		{
			$url = "/{$this->board_uri}/post/{$this->board_id}/{$action}";
		}
		
		return $url;
	}
	
	/**
	 * Fetches a URL for JSON requests that will update this thread or post.
	 *
	 * @param  boolean  $thread  If set to FALSE, will only provide a URl for single post (no reply) updates.
	 * @return string
	 */
	public function urlJson($thread = true)
	{
		$url = "";
		
		if ($thread)
		{
			if ($this->reply_to_board_id)
			{
				$url = "/{$this->board_uri}/thread/{$this->reply_to_board_id}.json";
			}
			else
			{
				$url = "/{$this->board_uri}/thread/{$this->board_id}.json";
			}
		}
		else
		{
			$url = "/{$this->board_uri}/post/{$this->board_id}.json";
		}
		
		return $url;
	}
	
	/**
	 * Fetches a URL for this post, with the reply-to hash.
	 *
	 * @return string
	 */
	public function urlReply()
	{
		$url = "";
		
		if ($this->reply_to_board_id)
		{
			$url = "/{$this->board_uri}/thread/{$this->reply_to_board_id}#reply-{$this->board_id}";
		}
		else
		{
			$url = "/{$this->board_uri}/thread/{$this->board_id}#reply-{$this->board_id}";
		}
		
		return $url;
	}
	
	/**
	 * Sends a redirect to the post's page.
	 *
	 * @param  string  $action
	 * @return Response
	 */
	public function redirect($action = null)
	{
		return redirect($this->url($action));
	}
	
	/**
	 * Pushes the post to the specified board, as a new thread or as a reply.
	 * This autoatically handles concurrency issues. Creating a new reply without
	 * using this method is forbidden by the `creating` event in ::boot.
	 *
	 *
	 * @param  App\Board  &$board
	 * @param  App\Post   &$thread
	 * @return void
	 */
	public function submitTo(Board &$board, &$thread = null)
	{
		$this->board_uri      = $board->board_uri;
		$this->author_ip      = new IP;
		$this->author_country = $board->getConfig('postsAuthorCountry', false) ? new Geolocation() : null;
		$this->reply_last     = $this->freshTimestamp();
		$this->bumped_last    = $this->reply_last;
		$this->setCreatedAt($this->reply_last);
		$this->setUpdatedAt($this->reply_last);
		
		if (!is_null($thread) && !($thread instanceof Post))
		{
			$thread = $board->getLocalThread($thread);
		}
		
		if ($thread instanceof Post)
		{
			$this->reply_to = $thread->post_id;
			$this->reply_to_board_id = $thread->board_id;
		}
		
		// Handle tripcode, if any.
		if (preg_match('/^([^#]+)?(##|#)(.+)$/', $this->author, $match))
		{
			// Remove password from name.
			$this->author = $match[1];
			// Whether a secure tripcode was requested, currently unused.
			$secure_tripcode_requested = ($match[2] == '##');
			// Convert password to tripcode, store tripcode hash in DB.
			$this->insecure_tripcode = ContentFormatter::formatInsecureTripcode($match[3]);
			
		}
		
		// Store the post in the database.
		DB::transaction(function() use ($board, $thread)
		{
			// The objective of this transaction is to prevent concurrency issues in the database
			// on the unique joint index [`board_uri`,`board_id`] which is generated procedurally
			// alongside the primary autoincrement column `post_id`.
			
			// First instruction is to add +1 to posts_total and set the last_post_at on the Board table.
			DB::table('boards')
				->where('board_uri', $this->board_uri)
				->increment('posts_total');
			
			DB::table('boards')
				->where('board_uri', $this->board_uri)
				->update([
					'last_post_at' => $this->created_at,
				]);
			
			// Second, we record this value and lock the table.
			$boards = DB::table('boards')
				->where('board_uri', $this->board_uri)
				->lockForUpdate()
				->select('posts_total')
				->get();
			
			$posts_total = $boards[0]->posts_total;
			
			// Third, we store a unique checksum for this post for duplicate tracking.
			$board->checksums()->create([
				'checksum' => $this->getChecksum(true),
			]);
			
			// Optionally, we also expend the adventure.
			$adventure = BoardAdventure::getAdventure($board);
			
			if ($adventure)
			{
				$this->adventure_id = $adventure->adventure_id;
				$adventure->expended_at = $this->created_at;
				$adventure->save();
			}
			
			// We set our board_id and save the post.
			$this->board_id  = $posts_total;
			$this->author_id = $this->makeAuthorId();
			$this->save();
			
			// Optionally, the OP of this thread needs a +1 to reply count.
			if ($thread instanceof static)
			{
				// We're not using the Model for this because it fails under high volume.
				
				$threadNewValues = [
					'updated_at'  => $thread->updated_at,
					'reply_last'  => $this->created_at,
					'reply_count' => $thread->replies()->count(),
				];
				
				if (!$this->isBumpless() && !$thread->isBumplocked())
				{
					$threadNewValues['bumped_last'] = $this->created_at;
				}
				
				DB::table('posts')
					->where('post_id', $thread->post_id)
					->update($threadNewValues);
			}
			
			// Queries and locks are handled automatically after this closure ends.
		});
		
		// Process uploads.
		$uploads = [];
		
		// Check file uploads.
		if (is_array($files = Input::file('files')))
		{
			$uploads = array_filter($files);
			
			if (count($uploads) > 0)
			{
				foreach ($uploads as $uploadIndex => $upload)
				{
					if(file_exists($upload->getPathname()))
					{
						FileStorage::createAttachmentFromUpload($upload, $this);
					}
				}
			}
		}
		else if(is_array($files = Input::get('files')))
		{
			$uniques  = [];
			$hashes   = $files['hash'];
			$names    = $files['name'];
			$spoilers = isset($files['spoiler']) ? $files['spoiler'] : [];
			
			$storages = FileStorage::whereIn('hash', $hashes)->get();
			
			foreach ($hashes as $index => $hash)
			{
				if (!isset($uniques[$hash]))
				{
					$uniques[$hash] = true;
					$storage = $storages->where('hash', $hash)->first();
					
					if ($storage && !$storage->banned)
					{
						$spoiler = isset($spoilers[$index]) ? $spoilers[$index] == 1 : false;
						
						$upload = $storage->createAttachmentWithThis($this, $names[$index], $spoiler, false);
						$upload->position = $index;
						$uploads[] = $upload;
					}
				}
			}
			
			$this->attachmentLinks()->saveMany($uploads);
			FileStorage::whereIn('hash', $hashes)->increment('upload_count');
		}
		
		
		// Finally fire event on OP, if it exists.
		if ($thread instanceof Post)
		{
			$thread->setRelation('board', $board);
			Event::fire(new ThreadNewReply($thread));
		}
		
		return $this;
	}
	
	
	/**
	 * Returns a thread with its replies for a thread view.
	 *
	 * @return static
	 */
	public function forThreadView()
	{
		$rememberTags    = ["board.{$this->board_uri}", "threads"];
		$rememberTimer   = 30;
		$rememberKey     = "board.{$this->board_uri}.thread.{$this->board_id}";
		$rememberClosure = function() {
			return $this->load(['replies' => function($query) {
				$query->withEverything();
				$query->orderBy('post_id', 'asc');
			}]);
		};
		
		switch (env('CACHE_DRIVER'))
		{
			case "file" :
			case "database" :
				$thread = Cache::remember($rememberKey, $rememberTimer, $rememberClosure);
				break;
			
			default :
				$thread = Cache::tags($rememberTags)->remember($rememberKey, $rememberTimer, $rememberClosure);
				break;
		}
		
		return $thread;
	}
}
