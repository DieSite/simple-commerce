<?php

namespace DuncanMcClean\SimpleCommerce\Orders;

use DuncanMcClean\SimpleCommerce\Customers\CustomerModel;
use DuncanMcClean\SimpleCommerce\Customers\EloquentCustomerRepository;
use DuncanMcClean\SimpleCommerce\SimpleCommerce;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use StatamicRadPack\Runway\Traits\HasRunwayResource;
use Illuminate\Support\Facades\DB;

class OrderModel extends Model
{
    use HasFactory, HasRunwayResource;

    protected $table = 'orders';

    protected $guarded = [];

    protected $casts = [
        'order_number' => 'integer',
        'items' => 'json',
        'grand_total' => 'integer',
        'items_total' => 'integer',
        'tax_total' => 'integer',
        'shipping_total' => 'integer',
        'coupon_total' => 'integer',
        'use_shipping_address_for_billing' => 'boolean',
        'gateway' => 'json',
        'data' => 'json',
    ];

    protected $appends = [
        'order_date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerModel::class);
    }

    public function statusLog(): HasMany
    {
        return $this->hasMany(StatusLogModel::class, 'order_id');
    }

    public function orderDate(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->statusLog
                    ->where('status', OrderStatus::Placed->value)
                    ->pluck('timestamp')
                    ->first();
            },
        );
    }

    public function scopeRunwayListing(Builder $query): Builder
    {
        return $query
            ->select('orders.*')
            ->leftJoin(DB::raw('(
                SELECT order_id, MIN(timestamp) as status_timestamp 
                FROM status_log 
                WHERE status = "placed"
                GROUP BY order_id
            ) as latest_status'), function ($join) {
                $join->on('orders.id', '=', 'latest_status.order_id');
            })
            ->orderByRaw('COALESCE(latest_status.status_timestamp, orders.created_at) DESC');
    }

    public function scopeRunwaySearch(Builder $query, string $searchQuery): Builder
    {
        $matchingUserIds = \Statamic\Facades\User::query()
            ->where('name', 'like', "%$searchQuery%")
            ->orWhere('email', 'like', "%$searchQuery%")
            ->get()
            ->pluck('id')
            ->toArray();

        return $query
            ->where(function ($query) use ($searchQuery, $matchingUserIds) {
                $query->where('order_number', 'like', "%$searchQuery%")
                    ->orWhere('grand_total', 'like', '%' . str_replace('.', '', $searchQuery) . '%')
                    ->orWhere('items_total', 'like', '%' . str_replace('.', '', $searchQuery) . '%')
                    ->orWhere(function($query) use ($searchQuery) {
                        $query->where('created_at', 'like', "%$searchQuery%")
                              ->where('order_status', '!=', 'cart');
                    })
                    ->orWhere('invoice_number', 'like', "%$searchQuery%")
                    ->orWhere('phonenumer', 'like', "%$searchQuery%")
                    ->orWhere('shipping_name', 'like', "%$searchQuery%")
                    ->orWhereRaw('JSON_UNQUOTE(customer_id) IN (?)', [implode('","', $matchingUserIds)]);
            });

    }

    protected function isOrExtendsClass(string $class, string $classToCheckAgainst): bool
    {
        return is_subclass_of($class, $classToCheckAgainst)
            || $class === $classToCheckAgainst;
    }
}
