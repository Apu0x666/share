<template>
    <fortus-calendar
        v-bind="obj.schema"
        v-model="input"
    />
</template>

<script>
export default {
    name: "FortusCalendarSchemaWrapper",
    props: ['type', 'value', 'obj'],
    data: () => ({
        localValue: null,
    }),
    computed:{
        input:{
            get(){
                return this.localValue ?? this.value
            },
            set(val){
                if(val){
                    this.localValue = val
                }
                return this.$emit('input', val)
            }
        }
    }
}
</script>

<style scoped>

</style>
