public function ticketSoldByDateRange(Request $request)
{
	$date_from = strtotime($request->input('date_from'));
	$date_to = strtotime($request->input('date_to') . ' +1 day');
	$orders = Order::whereIn('status', [2, 3])->where('create_time', '>', $date_from)->where('create_time', '<', $date_to)->get(['id']);
	$orders = $orders->pluck('id');
	$order_item = OrderItem::whereIn('orders_place_id', $orders)->get(['id', 'price', 'quantity', 'place_id', 'tickets']);

	$many_ticket = array();
	$order_item_ticket = $order_item_detail = [];

	$sgd_arr = [1, 2, 3, 7, 17];
	$rmb_arr = [4, 5, 6, 8, 9, 10, 13];

	$sgd_sale = $rmb_sale = $stripe_sale = $paypal_sale = $sale_sale = $qoo10_sale = $shopee_sale = $others_sale = [];

	foreach ($order_item as $oi)
	{
		$ticket_arr = explode(',', $oi->tickets);

		foreach ($ticket_arr as $t_a)//将ticket_id与orders_place_item_id关联  2019-01-16
		{
			if(!empty($t_a)) $order_item_ticket[$t_a] = $oi->id;
		}
		$order_item_detail[$oi->id] = ['price'=>$oi->price, 'quantity'=>$oi->quantity, 'place_id'=>$oi->place_id];//2019-01-16

		$count_ticket = count($ticket_arr) - 1;
		if($oi->quantity != $count_ticket)//判断是否存在一张ticket文件含有多张票
		{
			if($count_ticket == 1)
			{
			$many_ticket[$ticket_arr[0]] = $oi->quantity;
			}
		}
	}

	$source = $request->input('source');
	if($source == 0){
		$f = function($q){};				
	}else if($source == 1){
		$f = function($q){
			$q->where('origin_country', 1);					
		};				
	}else if($source == 2){
		$f = function($q){
			$q->where('origin_country', 0);					
		};				
	}
	$ticket = Ticket::whereIn('orders_place_id', $orders)->where($f)->get(['id', 'origin_price', 'origin_currency', 'place_id', 'orders_place_id']);

	$place_list = $ticket->pluck('place_id')->unique()->toArray();
	$place_array = implode(",", $place_list);
	$place = DB::select("select id, convert(cast(convert(title using latin1) as binary) using utf8) as title from places where id IN (".$place_array.")");
	$place_title = array();
	foreach($place as $p){
		$place_title[$p->id] = $p->title;
	}

	$return_list = $price_list = $qty_list = $cost_list = $qty_list2 = [];
	/*
	foreach($order_item as $oi){
		if(isset($price_list[$oi->place_id])){
			$price_list[$oi->place_id] += $oi->price * $oi->quantity;
			$qty_list[$oi->place_id] += $oi->quantity;
		}else{
			$price_list[$oi->place_id] = $oi->price * $oi->quantity;
			$qty_list[$oi->place_id] = $oi->quantity;
		}
	}
	*/
	foreach($ticket as $t){
		if(isset($cost_list[$t->place_id])){
			if($t->origin_currency == "SGD"){
				if(isset($many_ticket[$t->id]))
				{
					$cost_list[$t->place_id] += ($t->origin_price * $many_ticket[$t->id]);
					$qty_list2[$t->place_id] += $many_ticket[$t->id];
				}
				else
				{
					$cost_list[$t->place_id] += $t->origin_price;
					$qty_list2[$t->place_id]++;
				}
			}else if($t->origin_currency == "RMB"){
				$cost_list[$t->place_id] += ($t->origin_price / 5);						
			}
		}else{
			if(isset($many_ticket[$t->id]))
			{
				$cost_list[$t->place_id] = ($t->origin_price * $many_ticket[$t->id]);
				$qty_list2[$t->place_id] = $many_ticket[$t->id];
			}
			else
			{
				$cost_list[$t->place_id] = $t->origin_price;
				$qty_list2[$t->place_id] = 1;
			}

		}

		$oi_id = $order_item_ticket[$t->id];
		if(in_array($t->order->pay_mode_id, $sgd_arr))
		{
			if(!isset($sgd_sale[$t->place_id])) $sgd_sale[$t->place_id] = 0;
			$sgd_sale[$t->place_id] += $order_item_detail[$oi_id]['price'];
		}
		elseif(in_array($t->order->pay_mode_id, $rmb_arr))
		{
			if(!isset($rmb_sale[$t->place_id])) $rmb_sale[$t->place_id] = 0;
			$rmb_sale[$t->place_id] += $order_item_detail[$oi_id]['price'];
		}
		else
		{
			if($t->order->pay_mode_id == 12)//stripe
			{
				if(!isset($stripe_sale[$t->place_id])) $stripe_sale[$t->place_id] = 0;
				$stripe_sale[$t->place_id] += $order_item_detail[$oi_id]['price'];
			}
			elseif($t->order->pay_mode_id == 11)//PayPal
			{
				if(!isset($paypal_sale[$t->place_id])) $paypal_sale[$t->place_id] = 0;
				$paypal_sale[$t->place_id] += $order_item_detail[$oi_id]['price'];
			}
			elseif($t->order->pay_mode_id == 16)//qoo10
			{
				if(!isset($qoo10_sale[$t->place_id])) $qoo10_sale[$t->place_id] = 0;
				$qoo10_sale[$t->place_id] += $order_item_detail[$oi_id]['price'];
			}
			elseif($t->order->pay_mode_id == 18)//shopee
			{
				if(!isset($shopee_sale[$t->place_id])) $shopee_sale[$t->place_id] = 0;
				$shopee_sale[$t->place_id] += $order_item_detail[$oi_id]['price'];
			}
			elseif($t->order->pay_mode_id == 19)//sale
			{
				if(!isset($sale_sale[$t->place_id])) $sale_sale[$t->place_id] = 0;
				$sale_sale[$t->place_id] += $order_item_detail[$oi_id]['price'];
			}
			else//others
			{
				if(!isset($others_sale[$t->place_id])) $others_sale[$t->place_id] = 0;
				$others_sale[$t->place_id] += $order_item_detail[$oi_id]['price'];
			}
		}
	}

	foreach($place_list as $p){
		$temp = array();				
		$temp['place'] = $place_title[$p];
		/*
		$temp['price'] = $price_list[$p];
		$temp['qty'] = $qty_list[$p];				
		*/

		if(isset($cost_list[$p])){
			$temp['cost'] = $cost_list[$p];
			$temp['qty'] = $qty_list2[$p];

			if(isset($sgd_sale[$p]))
			{
				$temp['sgd_sale'] = $sgd_sale[$p];
			}
			else
			{
				$temp['sgd_sale'] = 0;
			}

			if(isset($rmb_sale[$p]))
			{
				$temp['rmb_sale'] = $rmb_sale[$p];
			}
			else
			{
				$temp['rmb_sale'] = 0;
			}

			if(isset($stripe_sale[$p]))
			{
				$temp['stripe_sale'] = $stripe_sale[$p];
			}
			else
			{
				$temp['stripe_sale'] = 0;
			}

			if(isset($paypal_sale[$p]))
			{
				$temp['paypal_sale'] = $paypal_sale[$p];
			}
			else
			{
				$temp['paypal_sale'] = 0;
			}

			if(isset($qoo10_sale[$p]))
			{
				$temp['qoo10_sale'] = $qoo10_sale[$p];
			}
			else
			{
				$temp['qoo10_sale'] = 0;
			}

			if(isset($shopee_sale[$p]))
			{
				$temp['shopee_sale'] = $shopee_sale[$p];
			}
			else
			{
				$temp['shopee_sale'] = 0;
			}

			if(isset($sale_sale[$p]))
			{
				$temp['sale_sale'] = $sale_sale[$p];
			}
			else
			{
				$temp['sale_sale'] = 0;
			}

			if(isset($others_sale[$p]))
			{
				$temp['others_sale'] = $others_sale[$p];
			}
			else
			{
				$temp['others_sale'] = 0;
			}
		}
		$return_list[] = $temp;
	}

	usort($return_list, function ($item1, $item2) {
	return $item2['cost'] <=> $item1['cost'];
	});

	return json_encode($return_list);
}
