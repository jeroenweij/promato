namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $table = 'Projects';
    protected $primaryKey = 'Id';
    public $timestamps = false;
}

