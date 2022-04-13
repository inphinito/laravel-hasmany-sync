<?php

namespace Inphinito\EloquentHasManySync;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\ServiceProvider;

class SyncHasManyProvider extends ServiceProvider
{
	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		HasMany::macro('sync', function (array $data, $deleting = true) {
			$changes = [
				'created' => [],
				'deleted' => [],
				'updated' => [],
			];

			/** @var HasMany $this */

			// Get the primary key.
			$relatedKeyName = $this->getRelated()->getKeyName();

			// Get the current key values.
			$current = $this->newQuery()->pluck($relatedKeyName)->all();

			// Cast the given key to an integer if it is numeric.
			$castKey = function ($value) {
				if (is_null($value)) {
					return $value;
				}

				return is_numeric($value) ? (int)$value : (string)$value;
			};

			// Cast the given keys to integers if they are numeric and string otherwise.
			$castKeys = function ($keys) use ($castKey) {
				return (array)array_map(function ($key) use ($castKey) {
					return $castKey($key);
				}, $keys);
			};

			// Get any non-matching rows.
			$deletedKeys = array_diff(
				$current,
				$castKeys(
					array_column($data, $relatedKeyName)
				)
			);

			if ($deleting && count($deletedKeys) > 0) {
				$this->getRelated()->destroy($deletedKeys);
				$changes['deleted'] = $deletedKeys;
			}

			// Separate the submitted data into "update" and "new"
			// We determine "newRows" as those whose $relatedKeyName (usually 'id') is null.
			$newRows = array_filter($data, function ($row) use ($relatedKeyName) {
				return empty($row[$relatedKeyName]);
			});

			// We determine "updateRows" as those whose $relatedKeyName (usually 'id') is set, not null.
			$updatedRows = array_filter($data, function ($row) use ($relatedKeyName) {
				return !empty($row[$relatedKeyName]);
			});

			if (count($newRows) > 0) {
				$newRecords = $this->createMany($newRows);
				$changes['created'] = $castKeys(
					$newRecords->pluck($relatedKeyName)->toArray()
				);
			}

			foreach ($updatedRows as $row) {
				$this->getRelated()->where($relatedKeyName, $castKey($row[$relatedKeyName]))
					->update($row);
			}

			$changes['updated'] = $castKeys(array_column($updatedRows, $relatedKeyName));

			return $changes;
		});
	}
}
