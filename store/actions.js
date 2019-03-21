import { getLocation } from '~/utils/helpers'

let timer;

export default {
	nuxtServerInit (store, context)
	{
		const headers = context.req && context.req.headers;

		if (headers.cookie === undefined)
			headers.cookie = '';

		return context.app.$get(context.route.fullPath, {
			initial: 'Y'
		})
		.then((data) =>
		{
			for (let key in data)
			{
				if (data.hasOwnProperty(key))
					store.state[key] = data[key];
			}

			if (data.route.controller === 'error')
				throw new Error('Страница не найдена')
		})
		.catch((e) => {
			return context.error(e);
		})
	},
	loadPage ({ state, commit }, url)
	{
		if (state.page !== null)
		{
			let page = JSON.parse(JSON.stringify(state.page))

			commit('PAGE_LOAD', {
				page: null
			})

			return new Promise((resolve) =>
			{
				return resolve({
					page
				});
			})
		}

		clearTimeout(timer)
		timer = setTimeout(() => {
			commit('setLoadingStatus', true)
		}, 1000)

		return this.$get(url).then((data) =>
		{
			let loc = getLocation(url);

			if (loc['pathname'] !== data['url'])
				this.$router.replace(data['url'])
			else
			{
				if (typeof data['tutorial'] !== 'undefined' && data['tutorial']['popup'] !== '')
				{
					$.confirm({
						title: 'Обучение',
						content: data['tutorial']['popup'],
						confirmButton: 'Продолжить',
						cancelButton: false,
						backgroundDismiss: false,
						confirm: () =>
						{
							if (data['tutorial']['url'] !== '')
								this.$router.push(data['tutorial']['url']);
						}
					});
				}

				if (typeof data['tutorial'] !== 'undefined' && data['tutorial']['toast'] !== '')
				{
					this.$toasted.show(data['tutorial']['toast'], {
						type: 'info'
					});
				}

				let page = JSON.parse(JSON.stringify(data.page))

				delete data.page

				clearTimeout(timer)

				commit('PAGE_LOAD', data)
				commit('setLoadingStatus', false)

				return {
					page
				};
			}
		});
	}
};