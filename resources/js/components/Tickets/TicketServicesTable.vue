<!--suppress EqualityComparisonWithCoercionJS -->
<template>
    <v-card>
        <v-card-title>
            <strong class="mr-5">Оказанные услуги</strong>
            <ticket-services-modal
                :ticket-id="ticketId"
                :ticket-title="ticketTitle"
                @change="onServiceChanged"
            >Добавить оказанную услугу</ticket-services-modal>
        </v-card-title>
        <v-card-text>
            <v-data-table
                :headers="headersWithControls"
                :items="itemsFiltered"
                :options="{pages: -1, itemsPerPage: -1}"
                :item-class="itemClass"
                selectable-key="id"
                show-select
                v-model="selected"
            >
                <template v-for="header in headers" v-slot:[`header.${header.value}`]>
                    <div style="min-height: 80px;">
                        <div >{{header.text}}</div>
                        <div v-if="header.dictionary" @click.stop.prevent>
                            <v-autocomplete v-model="filter[header.value]" :items="getDictionary(header.dictionary)" clearable/>
                        </div>
                    </div>
                </template>
                <template v-for="header in headersWithControls" v-slot:[`item.${header.value}`]="{item}">
                    <template v-if="header.value === 'controls'">
                        <ticket-services-modal
                            icon
                            :item="item"
                            :ticket-id="ticketId"
                            :ticket-title="ticketTitle"
                            @change="onServiceChanged"
                        >
                            <v-icon small>mdi-pencil</v-icon>
                        </ticket-services-modal>
                    </template>
                    <template v-else-if="typeof item[header.value] === 'boolean'">
                        <v-simple-checkbox
                            :color="item[header.value]?'green':'red'"
                            v-model="item[header.value]"
                            disabled
                        ></v-simple-checkbox>
<!--                        <v-icon :color="item[header.value]?'green':'red'" light>mdi-{{item[header.value]?'check-bold':'close'}}</v-icon>-->
                    </template>
                    <template v-else>
                        {{itemValue(item, header)}}
                    </template>
                </template>
                <template #footer.prepend>
                    Выбрано {{selectedCount}} строк:
                    <v-btn
                        :disabled="selectedCount < 1 || batchSyncButtonDisabled"
                        class="ma-2" x-small color="green"
                        @click="syncServices"
                        :loading="batchSyncButtonDisabled"
                    >Синхронизировать</v-btn>
                    <v-btn
                        @click.prevent="batchDelete"
                        :disabled="selectedCount < 1"
                        class="ma-2 white--text" x-small color="red"
                    >Удалить</v-btn>
                </template>
            </v-data-table>
            <v-snackbar
                elevation="20"
                color="primary"
                v-model="showMessage"
                :timeout="3000"
                absolute right bottom multi-line outlined
            ><pre>{{message}}</pre></v-snackbar>
        </v-card-text>
    </v-card>
</template>

<script>
import {
    mapState
} from "vuex";
import TicketServicesModal
    from "./TicketServicesModal";
import clone
    from "just-clone";
import axios
    from "axios";

export default {
    name: "TicketServicesTable",
    components: {TicketServicesModal},
    props: {
        ticketId: Number,
        ticketTitle: String,
        headers: Array,
        items: Array,
    },
    data: () => ({
        changed: {},
        added: [],
        filter: {},
        batchSyncButtonDisabled: false,
        message: 'Тестовое сообщение',
        showMessage: false,
        selected: [],
    }),
    computed: {
        ...mapState('dictionary', ['dictionary']),
        selectedCount(){
            return this.selected?.length ?? 0
        },
        selectedIds(){
            return this.selected?.map(v => v.id)
        },
        headersWithControls(){
            return [{value: 'controls'},...this.headers]
        },
        itemsWithChanged(){
            let ticketServices = clone(this.items)
            let res = [];
            for(let item of [...ticketServices, ...this.added]){
                item.service_type_id = Number(item.service_type_id)

                let softwareDictionary = this.getDictionary('software').map(function (item) {
                    return {
                        'text': item.text + ((item.version) ? ' ' + item.version : ''),
                        'type_id': item.type_id,
                        'value': item.value,
                        'version': item.version
                    }
                });
                
                let currSoft = item.software.split(',').map(i => i.trim());
                let newSoft = softwareDictionary.map(function (dictItem) {

                    if (currSoft.find(v => v === dictItem.text)) {
                        currSoft[currSoft.indexOf(dictItem.text)] = undefined;
                        return {
                            'text': dictItem.text,
                            'type_id': dictItem.type_id,
                            'value': dictItem.value,
                            'version': dictItem.version
                        }
                    }
                });
                newSoft = newSoft.filter(function (el) {
                    return el != null;
                });
                currSoft = currSoft.filter(function (el) {
                    return el != null;
                });
    
                currSoft.map(function (el){
                    newSoft.push({
                        'text': el,
                    })
                });
                
                item.software_text = item.software;  //для визуального вывода в под-таблице сервисов
                item.software = newSoft; //для вывода в карточке заявки (чтобы исключались уже выбранные и т.п.)

                let toPush = this.changed[item.id] ?? item
                if(!(toPush.deleted && !toPush.bitrix_id)){
                    res.push(toPush)
                }
            }
            return res
        },
        itemsFiltered(){
            if(Object.values(this.filter)?.filter(v => !!v).length > 0){
                let keys = Object.keys(this.filter)
                return  this.itemsWithChanged.filter(v => {
                    let res = true
                    for(let key of keys){
                        let filterVal = this.filter[key]
                        let itemVal = v[key]
                        if(!!filterVal || filterVal === 0) {
                            if (typeof itemVal === 'string' && !itemVal?.includes?.(filterVal)) {
                                res = false
                            } else if (itemVal != filterVal) {
                                res = false
                            }
                        }
                    }
                    return res
                })
            } else {
                return this.itemsWithChanged
            }
        },
        itemsClasses(){
            let dict = {}
            for (let item of this.itemsWithChanged){
                let res = `service_${item.id} `
                if(item.deleted) {
                    res += 'deleted_item';
                } else if(item.synced){
                    res += 'default'
                } else {
                    res += 'unsynced_item'
                }
                dict[item.id] = res
            }
            return dict
        }
    },
    mounted() {
    },
    methods: {
        getDictionary(title){
            return this.dictionary?.[title] ?? []
        },
        itemValue(item, header){
            let val = item[header.value]
            if (header.value == 'software') {
                return item.software_text;
            }
            if(header.dictionary){
                return  this.getDictionary(header.dictionary)?.find(v => v.value == val)?.text
            }
            return  val
        },
        onServiceChanged({changed, isNew}){
            if(isNew){
                changed.serviceTypeTitle = this.dictionary?.service_type?.find(v => v.value === changed.service_type_id)?.text
                this.added.push(changed)
            } else {
                this.$set(this.changed, changed.id, changed)
            }
            this.$emit('change')
        },
        itemClass(item){
            return this.itemsClasses[item.id]
        },
        async batchDelete(){
            if(this.selectedIds.length) {
                await axios.delete('/tickets/0/services/0', {data: {ids: this.selectedIds}})
                for(let item of this.selected){
                    this.changed[item.id] = {
                        ...item,
                        deleted: true
                    }
                }
                this.selected = []
                this.afterSync()
            }
        },
        async syncServices(){
            if(this.selectedIds.length) {
                this.batchSyncButtonDisabled = true
                let res = await axios.post('sync', {services_ids: this.selectedIds})
                this.batchSyncButtonDisabled = false
                this.afterSync(res)
            }
        },
        afterSync(res = null){
            this.selected = []
            this.$emit('afterSync', res)
        }
    }
}
</script>

<style scoped>

</style>
