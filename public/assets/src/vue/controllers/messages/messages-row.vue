<template>
	<div>
		<div class="row">
			<div class="col-1 th text-center">
				<input name="delete[]" type="checkbox" :value="item['id']" v-model="item['deleted']" title="Удалить">
			</div>
			<div class="col-3 th text-center">{{ item['time']|date('d.m.y H:i:s') }}</div>
			<div class="col-6 th text-center">
				<popup-link v-if="item['from'] > 0" :to="'/players/'+item['from']+'/'">
					{{ item['theme'] }}
				</popup-link>
				<span v-else>
					{{ item['theme'] }}
				</span>
			</div>
			<div class="col-2 th text-center">
				<span v-if="item['type'] === 1">
					<router-link :to="'/messages/write/'+item['from']+'/'" title="Ответить">
						<span class="sprite skin_m"></span>
					</router-link>
					<router-link :to="'/messages/write/'+item['from']+'/quote/'+item['id']+'/'" title="Цитировать сообщение">
						<span class="sprite skin_z"></span>
					</router-link>
					<a @click.prevent="abuseAction" title="Отправить жалобу">
						<span class="sprite skin_s"></span>
					</a>
				</span>
			</div>
		</div>
		<div class="row">
			<div :style="'background-color:'+$root.getLang('MESSAGE_TYPES_BACKGROUNDS', item['type'])" class="col-12 b">
				<div v-if="$parent.$parent.page['parser']">
					<text-viewer :text="item['text']"></text-viewer>
				</div>
				<div v-else v-html="item['text']"></div>
			</div>
		</div>
	</div>
</template>

<script>
	export default {
		name: "messages-row",
		props: {
			item: Object
		},
		methods: {
			abuseAction ()
			{
				$.confirm({
					content: 'Вы уверены что хотите отправить жалобу на это сообщение?',
					title: 'Сообщения',
					backgroundDismiss: true,
					buttons: {
						confirm: {
							text: 'да',
							action: () => {
								this.$router.push('/messages/abuse/'+item['id']+'/')
							}
						},
						cancel: {
							text: 'нет'
						}
					}
				});
			}
		}
	}
</script>