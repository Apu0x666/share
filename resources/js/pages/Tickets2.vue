<template>
    <g-layout>
<!--        <v-tabs >-->
<!--            <v-tab-->
<!--                v-for="link in links"-->
<!--                :key="link.title"-->
<!--                :href="link.href"-->
<!--                as="v-tab" href="/" tag="inertia-link" :exact="true">{{link.title}}</v-tab>-->
<!--        </v-tabs>-->
        <v-data-table
            :headers="headers"
            :items="items"
            :expanded.sync="expanded"
            show-expand
            item-key="ID"
        >
            <template #item.STAGE_ID="{value}">
                {{stageIds[value].label}}
            </template>
            <template v-slot:expanded-item="{ headers, item }">
                <td :colspan="headers.length">
                    <pre>{{item}}</pre>
                </td>
            </template>
        </v-data-table>
    </g-layout>
</template>
<script>
const STAGE_ID = [
    {label: 'Новый', value: 'NEW'},
    {label: 'В работе', value: 'EXECUTING'},
    {label: 'Подготовка отчета', value: '1'},
    {label: 'Ожидание обратной связи', value: '2'},
    {label: 'Решено (Услуги оказаны)', value: 'WON'},
    {label: 'Отклонено (отказ)', value: 'LOSE'}
]
export default {
    name: "Home",
    props: [
        'items'
    ],
    data: () => ({
        expanded: null,
        links: [
            {title: 'ПППУР', href: '/'},
            {title: '004', href: '/'},
            {title: 'ТИОД ХРАП', href: '/'},
            {title: 'КСОМБ', href: '/'},
            {title: 'ЗАББИКС', href: '/'},
            {title: 'ТИОД ВИБ', href: '/'},
        ],
        headers: [
            {text: 'ID', value: 'ID'},
            {text: 'Инцидент', value: 'TITLE'},
            {text: 'Стадия', value: 'STAGE_ID'},
            {text: 'Получатель', value: 'CONTACT_ID'},
            {text: 'Дата начала', value: 'BEGINDATE'},
            {text: 'Дата завершения', value: 'CLOSEDATE'},
            {text: 'Закрыт', value: 'CLOSED'},
            {text: 'Источник', value: 'SOURCE_ID'},
            {text: 'Адрес', value: 'UF_HARD_ADDRESS'},
            {text: 'Система', value: 'UF_HARD_NAME'},
            {text: 'Приоритет', value: 'UF_PRIORITY'},
        ]
    }),
    computed: {
        stageIds(){
            let res = {}
            for(let item of STAGE_ID){
                res[item.value] = item
            }
            return res
        }
    },
    methods: {
    }
}
</script>

<style scoped>

</style>
